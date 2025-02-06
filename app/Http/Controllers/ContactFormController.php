<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use App\Models\ContactForm;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactFormController extends Controller
{
    public function submit(ContactFormRequest $request)
    {
        try {
            $contactForm = ContactForm::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'message' => $request->message,
                'status' => 'pending'
            ]);

            // Queue the email
            Mail::to(config('mail.contact_form_recipient'))
                ->queue(new ContactFormMail($contactForm));

            Log::info('Contact form submitted', [
                'contact_form_id' => $contactForm->id,
                'email' => $contactForm->email
            ]);

            return ApiResponse::success('Your message has been sent successfully. We will contact you soon.');
        } catch (\Exception $e) {
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return ApiResponse::failure('Failed to send your message. Please try again later.');
        }
    }
}
