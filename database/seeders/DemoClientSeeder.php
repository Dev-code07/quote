<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic Indian client data for local development (rules.md section 4).
 */
class DemoClientSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrFail();

        $clients = [
            ['name' => 'IIT Mandi', 'contact_person' => 'Dr. S. Kumar', 'phone' => '+91-98765-43210', 'gstin' => '02AAACI1234A1ZV', 'address' => 'Kamla Rani Nagar, Mandi, Himachal Pradesh 175001', 'is_active' => true],
            ['name' => 'ABC Technologies Pvt. Ltd.', 'contact_person' => 'Rajesh Menon', 'phone' => '+91-98110-22334', 'gstin' => '27AABCA1234F1Z6', 'address' => '4th Floor, Tower B, Cyber Park, Noida, Uttar Pradesh 201309', 'is_active' => true],
            ['name' => 'Radiant Healthcare Pvt. Ltd.', 'contact_person' => 'Anita Desai', 'phone' => '+91-22-6789-4410', 'gstin' => '27AAACR5678E1ZT', 'address' => 'Plot 14, Sector 18, Noida, Uttar Pradesh 201301', 'is_active' => true],
            ['name' => 'Sharma Enterprises', 'contact_person' => 'Vikram Sharma', 'phone' => '+91-98100-11223', 'gstin' => '07AAKFS5521M1Z3', 'address' => '112, Bhagirath Palace, Chandni Chowk, Delhi 110006', 'is_active' => true],
            ['name' => 'Himalayan Computers', 'contact_person' => 'Neha Gupta', 'phone' => '+91-1777-265441', 'gstin' => null, 'address' => 'Shop 7, Mall Road, Shimla, Himachal Pradesh 171001', 'is_active' => true],
            ['name' => 'Tagore Public School', 'contact_person' => 'Principal Office', 'phone' => '+91-94360-88990', 'gstin' => null, 'address' => 'Civil Lines, Dharwad, Karnataka 580004', 'is_active' => false],
        ];

        foreach ($clients as $client) {
            Client::query()->updateOrCreate(
                ['name' => $client['name']],
                [...$client, 'created_by' => $admin->id]
            );
        }
    }
}
