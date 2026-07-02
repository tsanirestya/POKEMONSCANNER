const DB_NAME = 'pokemonscanner';
const DB_VERSION = 1;
const STORE_PRODUCTS = 'products';
const STORE_QUEUE = 'queue';
const LAST_SYNC_KEY = 'pokemonscanner:last-sync-at';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);

        req.onupgradeneeded = () => {
            const db = req.result;

            if (!db.objectStoreNames.contains(STORE_PRODUCTS)) {
                db.createObjectStore(STORE_PRODUCTS, { keyPath: 'barcode' });
            }

            if (!db.objectStoreNames.contains(STORE_QUEUE)) {
                db.createObjectStore(STORE_QUEUE, { keyPath: 'scan_uuid' });
            }
        };

        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function withStore(storeName, mode, fn) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, mode);
        const store = tx.objectStore(storeName);
        const result = fn(store);

        tx.oncomplete = () => resolve(result);
        tx.onerror = () => reject(tx.error);
    });
}

export async function refreshMasterCache() {
    const res = await fetch('/scan/master-cache', {
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) throw new Error('gagal ambil master cache');

    const data = await res.json();

    await withStore(STORE_PRODUCTS, 'readwrite', (store) => {
        store.clear();
        data.products.forEach((p) => store.put(p));
    });

    return data.products.length;
}

export async function getCachedProduct(barcode) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_PRODUCTS, 'readonly');
        const req = tx.objectStore(STORE_PRODUCTS).get(barcode);

        req.onsuccess = () => resolve(req.result || null);
        req.onerror = () => reject(req.error);
    });
}

export async function applyOptimisticLocalStock(barcode, tipe) {
    await withStore(STORE_PRODUCTS, 'readwrite', (store) => {
        const req = store.get(barcode);
        req.onsuccess = () => {
            const product = req.result;
            if (!product) return;
            product.stok_sekarang += tipe === 'in' ? 1 : -1;
            store.put(product);
        };
    });
}

export async function queueScan(item) {
    await withStore(STORE_QUEUE, 'readwrite', (store) => store.put(item));
}

export async function getQueue() {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_QUEUE, 'readonly');
        const req = tx.objectStore(STORE_QUEUE).getAll();

        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function getQueueCount() {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_QUEUE, 'readonly');
        const req = tx.objectStore(STORE_QUEUE).count();

        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function removeFromQueue(scanUuid) {
    await withStore(STORE_QUEUE, 'readwrite', (store) => store.delete(scanUuid));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function submitToServer(item) {
    const res = await fetch('/scan/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            barcode: item.barcode,
            tipe: item.tipe,
            scan_uuid: item.scan_uuid,
        }),
    });

    if (!res.ok) throw new Error('http-' + res.status);

    return res.json();
}

/**
 * Kirim antrian satu per satu (FIFO). Berhenti begitu satu item gagal
 * karena jaringan (sisanya dicoba lagi di sync berikutnya).
 */
export async function flushQueue(onItemSynced) {
    const items = await getQueue();
    let syncedCount = 0;

    for (const item of items) {
        try {
            await submitToServer(item);
            await removeFromQueue(item.scan_uuid);
            syncedCount++;
            if (onItemSynced) onItemSynced(item, syncedCount);
        } catch (e) {
            // gagal (masih offline / server error) — hentikan, sisa antrian dicoba lagi nanti
            break;
        }
    }

    if (syncedCount === items.length && items.length > 0) {
        setLastSyncAt(new Date());
    } else if (items.length === 0) {
        setLastSyncAt(new Date());
    }

    return syncedCount;
}

export function setLastSyncAt(date) {
    localStorage.setItem(LAST_SYNC_KEY, date.toISOString());
}

export function getLastSyncAt() {
    const raw = localStorage.getItem(LAST_SYNC_KEY);

    return raw ? new Date(raw) : null;
}

export { submitToServer };
