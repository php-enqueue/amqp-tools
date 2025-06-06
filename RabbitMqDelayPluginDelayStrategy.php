<?php

declare(strict_types=1);

namespace Enqueue\AmqpTools;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Queue\Exception\InvalidDestinationException;

class RabbitMqDelayPluginDelayStrategy implements DelayStrategy
{
    public function delayMessage(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, int $delay): void
    {
        $delayMessage = $context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $delayMessage->setProperty('x-delay', (int) $delay);
        $delayMessage->setRoutingKey($message->getRoutingKey());

        if ($dest instanceof AmqpTopic) {
            $delayTopic = $context->createTopic('enqueue.'.$dest->getTopicName().'.delayed');
            $delayTopic->setType('x-delayed-message');
            $delayTopic->addFlag($dest->getFlags());
            $delayTopic->setArgument('x-delayed-type', $dest->getType());

            $context->declareTopic($delayTopic);
            $context->bind(new AmqpBind($dest, $delayTopic, $delayMessage->getRoutingKey()));
        } elseif ($dest instanceof AmqpQueue) {
            $delayTopic = $context->createTopic('enqueue.queue.delayed');
            $delayTopic->setType('x-delayed-message');
            $delayTopic->addFlag(AmqpTopic::FLAG_DURABLE);
            $delayTopic->setArgument('x-delayed-type', AmqpTopic::TYPE_DIRECT);

            $delayMessage->setRoutingKey($dest->getQueueName());

            $context->declareTopic($delayTopic);
            $context->bind(new AmqpBind($dest, $delayTopic, $delayMessage->getRoutingKey()));
        } else {
            throw new InvalidDestinationException(sprintf('The destination must be an instance of %s but got %s.', AmqpTopic::class.'|'.AmqpQueue::class, $dest::class));
        }

        $producer = $context->createProducer();

        if ($producer instanceof DelayStrategyAware) {
            $producer->setDelayStrategy(null);
        }

        $producer->send($delayTopic, $delayMessage);
    }
}
