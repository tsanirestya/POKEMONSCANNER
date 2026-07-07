<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'booking_code' => 'BK-'.now()->format('ymd').'-'.strtoupper(fake()->unique()->bothify('##??')),
            'nomor_urut' => Booking::nextNomorUrut(),
            'user_id' => User::factory(),
            'status' => Booking::STATUS_PRINTED,
        ];
    }
}
