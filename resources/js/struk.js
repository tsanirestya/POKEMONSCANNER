import JsBarcode from 'jsbarcode';

// Struk thermal Booking Order (DEC-24): render booking_code sebagai Code128
// ke elemen <svg data-barcode="...">. Teks code dicetak terpisah di markup
// supaya gaya font konsisten dengan sisa struk.
document.querySelectorAll('svg[data-barcode]').forEach((el) => {
    JsBarcode(el, el.dataset.barcode, {
        format: 'CODE128',
        width: 2,
        height: 56,
        margin: 0,
        displayValue: false,
    });
});
