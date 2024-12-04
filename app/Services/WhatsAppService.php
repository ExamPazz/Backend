<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    private string $baseUrl;
    private string $accessToken;
    private string $phoneNumberId;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.api_url');
        $this->accessToken = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');

        if (!$this->accessToken || !$this->phoneNumberId) {
            throw new Exception("WhatsApp API credentials are not configured correctly.");
        }
    }

    /**
     * Sends a WhatsApp message to a recipient.
     *
     * @param string $recipient The recipient's WhatsApp phone number (including country code).
     * @param string $message The message content to send.
     * @return array The API response as an associative array.
     * @throws Exception If the API call fails.
     */
    public function sendMessage(string $recipient, string $message): array
    {
        $endpoint = sprintf('%s/%s/messages', $this->baseUrl, $this->phoneNumberId);

        try {
            $response = Http::withToken($this->accessToken)
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->failed()) {
                Log::error("WhatsApp API error", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception("Failed to send message. Status: {$response->status()}.");
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error("WhatsAppService::sendMessage Exception", ['message' => $e->getMessage()]);
            throw $e;
        }
    }
}
