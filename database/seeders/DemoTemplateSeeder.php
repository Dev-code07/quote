<?php

namespace Database\Seeders;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic Indian template data for local development.
 */
class DemoTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrFail();

        $templates = [
            [
                'name' => 'Standard Business Quote',
                'is_default' => true,
                'accent_color' => AccentPalette::Teal,
                'header_alignment' => HeaderAlignment::Center,
                'doc_title' => 'QUOTATION',
                'company_name' => 'ABC Technologies Pvt. Ltd.',
                'company_gstin' => '09AABCA1234F1Z6',
                'tagline' => 'IT Hardware, Software Licensing & Networking',
                'address' => '4th Floor, Tower B, Cyber Park, Sector 62, Noida 201309 (U.P.)',
                'email' => 'sales@abctech.in',
                'mobile_1' => '98110-22334',
                'mobile_2' => '0120-4567890',
                'stamp_place' => 'Noida (U.P.)',
                'authorized_person' => 'Rajesh Menon',
                'designation' => 'Director',
                'default_gst_rate' => 18,
                'intro_message' => 'While thanking you for your esteemed enquiry no. {enquiry_no} dated {enquiry_date}, we submit our lowest rates as under for favour of {client_name}, subject to the terms and conditions given below.',
                'delivery_period' => '2-3 weeks from purchase order',
                'warranty' => 'One year (manufacturer)',
                'validity_text' => '15 days from the above date',
                'extra_terms' => "Payment: 50% advance, balance before delivery.\nInstallation charges extra where applicable.",
                'notes' => 'All prices are exclusive of transit insurance.',
            ],
            [
                'name' => 'Product Quotation',
                'is_default' => false,
                'accent_color' => AccentPalette::Maroon,
                'header_alignment' => HeaderAlignment::Left,
                'letterhead_display_name' => 'Sharma Ent.',
                'doc_title' => 'QUOTATION',
                'company_name' => 'Sharma Enterprises',
                'company_gstin' => '07AAKFS5521M1Z3',
                'tagline' => 'Electrical Goods, Switchgear, Wires & Cables',
                'address' => '112, Bhagirath Palace, Chandni Chowk, Delhi 110006',
                'email' => 'sharmaent.delhi@gmail.com',
                'mobile_1' => '98100-11223',
                'mobile_2' => '011-23861145',
                'stamp_place' => 'Delhi',
                'authorized_person' => 'Vikram Sharma',
                'designation' => 'Proprietor',
                'default_gst_rate' => 12,
                'intro_message' => 'Dear Sir/Madam, we are pleased to submit our quotation for {client_name} against your enquiry no. {enquiry_no} dated {enquiry_date}.',
                'delivery_period' => '7-10 days ex-stock',
                'warranty' => 'One year replacement warranty',
                'validity_text' => '30 days from the above date',
                'extra_terms' => 'Goods once sold will not be taken back.',
            ],
            [
                'name' => 'Service / Estimate',
                'is_default' => false,
                'accent_color' => AccentPalette::Forest,
                'header_alignment' => HeaderAlignment::Right,
                'doc_title' => 'ESTIMATE',
                'company_name' => 'Himalayan Computers',
                'company_gstin' => null,
                'tagline' => 'Laptops, Desktops, Printers & Peripherals',
                'address' => 'Shop 7, Mall Road, Shimla 171001 (H.P.)',
                'email' => 'hello@himalayancomputers.in',
                'mobile_1' => '0177-2654410',
                'mobile_2' => null,
                'stamp_place' => 'Shimla (H.P.)',
                'authorized_person' => 'Neha Gupta',
                'designation' => 'Manager',
                'default_gst_rate' => 18,
                'intro_message' => 'Thank you for your interest. Please find our estimate for {client_name} below.',
                'delivery_period' => 'Immediate on stock items',
                'warranty' => 'As per manufacturer warranty',
                'validity_text' => '7 days from the above date',
                'extra_terms' => 'Estimate is indicative and subject to stock availability.',
            ],
        ];

        foreach ($templates as $template) {
            QuoteTemplate::query()->updateOrCreate(
                ['name' => $template['name']],
                [...$template, 'created_by' => $admin->id]
            );
        }
    }
}
