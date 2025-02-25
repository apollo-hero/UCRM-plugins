<?php

declare(strict_types=1);

namespace SmsNotifier\Facade;

use SmsNotifier\Data\NotificationData;
use SmsNotifier\Data\PluginData;
use SmsNotifier\Factory\MessageTextFactory;
use SmsNotifier\Service\Logger;
use SmsNotifier\Service\OptionsManager;
use SmsNotifier\Service\SmsNumberProvider;
use Twilio\Rest\Client;

class TwilioNotifierFacade extends AbstractMessageNotifierFacade {

    /** @var Client */
    private $twilioClient;

    /** @var PluginData */
    private $pluginData;

    public function __construct(
        Logger $logger,
        MessageTextFactory $messageTextFactory,
        SmsNumberProvider $smsNumberProvider,
        OptionsManager $optionsManager
    )
    {
        parent::__construct($logger, $messageTextFactory, $smsNumberProvider);
        // load config data
        $this->pluginData = $optionsManager->load();
    }


    /*
     * Get Twilio SMS API object (unless it's already initialized)
     */
    public function getTwilioClient(): Client
    {
        if (!$this->twilioClient) {
            $this->twilioClient = new Client(
                $this->pluginData->twilioAccountSid,
                $this->pluginData->twilioAuthToken
            );
        }
        return $this->twilioClient;
    }

    /*
     * Send message through the Twilio client
     */
    protected function sendMessage(
        NotificationData $notificationData,
        string $clientSmsNumber,
        string $messageBody
    ): void
    {
        $this->logger->debug(sprintf('Sending: %s', $messageBody));

        $messageInstance = $this->getTwilioClient()->messages->create(
            $clientSmsNumber,
            [
                'from' => $this->getSenderNumber(),
                'body' => $messageBody,
            ]
        );

        $this->logger->debug((string) $messageInstance);
        $this->logger->info(sprintf('Twilio status: %s, message id: %s', $messageInstance->status, $messageInstance->sid));
        if ($messageInstance->errorCode) {
            $this->logger->warning(sprintf('Twilio error: %s %s', $messageInstance->errorCode, $messageInstance->errorMessage));
        }
    }

    /*
     * Phone number of sender - required by Twilio. In this plugin, we only load it from config.
     */
    private function getSenderNumber(): string
    {
        return $this->pluginData->twilioSmsNumber;
    }
}
