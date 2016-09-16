<?php

namespace Saxulum\Tests\ProcessesExecutor\Resources;

use Saxulum\MessageQueue\MessageInterface;

class SampleMessage implements MessageInterface
{
    /**
     * @var string
     */
    private $context;

    /**
     * @var string
     */
    private $message;

    /**
     * @param string $context
     * @param string $message
     */
    public function __construct(string $context, string $message)
    {
        $this->context = $context;
        $this->message = $message;
    }

    /**
     * @param string $json
     *
     * @return MessageInterface
     */
    public static function fromJson(string $json): MessageInterface
    {
        $rawMessage = json_decode($json);

        return new self($rawMessage->context, $rawMessage->message);
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'context' => $this->context,
            'message' => $this->message,
        ]);
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
