<?php

namespace Database\Seeders;

use App\Actions\Forms\SaveFields;
use App\Models\Form;
use Illuminate\Database\Seeder;

/**
 * One working form to start from, so the builder opens on something real
 * rather than an empty screen.
 */
class FormSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Form::where('slug', 'contact')->first();

        // Never rebuilt: re-seeding must not throw away fields someone edited.
        if ($existing) {
            return;
        }

        $form = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'description' => 'The general enquiry form.',
            'status' => 'Active',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks — we will reply within one working day.',
            'notify_email' => 'hello@example.com',
            'store_submissions' => true,
        ]);

        app(SaveFields::class)->handle($form, [
            ['label' => 'Your name', 'name' => 'name', 'type' => 'text', 'width' => 'half', 'required' => true,
                'placeholder' => 'Jane Rahman'],
            ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'width' => 'half', 'required' => true,
                'placeholder' => 'jane@example.com'],
            ['label' => 'Phone', 'name' => 'phone', 'type' => 'tel', 'width' => 'half', 'required' => false,
                'help' => 'Only if you would rather we called.'],
            ['label' => 'Subject', 'name' => 'subject', 'type' => 'select', 'width' => 'half', 'required' => true,
                'options' => ['General enquiry', 'Support', 'Billing', 'Something else']],
            ['label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'width' => 'full', 'required' => true,
                'rule' => 'min:10', 'help' => 'A few sentences is plenty.'],
            ['label' => 'I agree to be contacted about this enquiry', 'name' => 'consent', 'type' => 'checkbox',
                'width' => 'full', 'required' => true],
        ]);
    }
}
