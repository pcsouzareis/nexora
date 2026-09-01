<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WebhookRepository;
use InvalidArgumentException;
use RuntimeException;

final class WebhookMessageProcessor
{
    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly AIMessageResponder $responder
    ) {}

    /**
     * @param array{external_id: string, name: string|null, conversation_id: string, message_id: string, base_id: int, message: string} $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function process(int $companyCode, int $channelCode, array $data): array
    {
        if (!$this->webhooks->baseBelongsToCompany($companyCode, $data['base_id'])) {
            throw new InvalidArgumentException('Base não encontrada para este canal.');
        }

        $clientCode = $this->webhooks->findOrCreateClient(
            $companyCode,
            $data['external_id'],
            $data['name']
        );
        $conversationCode = $this->webhooks->findOrCreateConversation(
            $companyCode,
            $channelCode,
            $clientCode,
            $data['base_id'],
            $data['conversation_id']
        );

        $existing = $this->webhooks->findIncomingMessageByExternalId(
            $conversationCode,
            $data['message_id']
        );

        if ($existing !== null) {
            $reply = $this->webhooks->findChatbotReply((int) $existing['cod009']);

            return [
                'status' => 200,
                'body' => [
                    'received' => true,
                    'duplicate' => true,
                    'conversation_id' => $conversationCode,
                    'message_id' => (int) $existing['cod009'],
                    'reply' => $reply === null ? null : [
                        'message_id' => (int) $reply['cod009'],
                        'message' => (string) $reply['con009'],
                    ],
                ],
            ];
        }

        $messageCode = $this->webhooks->createIncomingMessage(
            $conversationCode,
            $data['message_id'],
            $data['message']
        );

        // Quando um atendente assumiu a conversa, a IA deixa de responder.
        if ($this->webhooks->isHumanHandling($companyCode, $conversationCode)) {
            $this->webhooks->markWaitingForAgent($companyCode, $conversationCode);
            return [
                'status' => 202,
                'body' => [
                    'received' => true,
                    'processed' => false,
                    'human_handling' => true,
                    'conversation_id' => $conversationCode,
                    'message_id' => $messageCode,
                    'message' => 'Um atendente assumiu esta conversa.',
                ],
            ];
        }

        try {
            $context = $this->webhooks->findAIContext($companyCode, $data['base_id']);

            if ($context === null) {
                throw new RuntimeException('A configuração da IA não foi encontrada.');
            }

            $answer = $this->responder->respond(
                $context,
                $this->webhooks->findPublicKnowledge($data['base_id']),
                $data['message']
            );

            if ($answer['requires_human']) {
                $this->webhooks->markForHumanHandoff($companyCode, $conversationCode);
                $handoffMessage = 'Não localizei uma informação segura para responder agora. Um atendente dará continuidade ao seu atendimento.';
                $replyCode = $this->webhooks->createChatbotReply(
                    $conversationCode,
                    $messageCode,
                    $handoffMessage,
                    $answer['tokens']
                );

                return [
                    'status' => 202,
                    'body' => [
                        'received' => true,
                        'processed' => false,
                        'human_handoff' => true,
                        'conversation_id' => $conversationCode,
                        'message_id' => $messageCode,
                        'reply' => [
                            'message_id' => $replyCode,
                            'message' => $handoffMessage,
                        ],
                    ],
                ];
            }

            $replyCode = $this->webhooks->createChatbotReply(
                $conversationCode,
                $messageCode,
                $answer['text'],
                $answer['tokens']
            );
        } catch (RuntimeException) {
            return [
                'status' => 202,
                'body' => [
                    'received' => true,
                    'processed' => false,
                    'conversation_id' => $conversationCode,
                    'message_id' => $messageCode,
                    'error' => 'Mensagem recebida, mas a IA não pôde responder neste momento.',
                ],
            ];
        }

        return [
            'status' => 201,
            'body' => [
                'received' => true,
                'processed' => true,
                'conversation_id' => $conversationCode,
                'message_id' => $messageCode,
                'reply' => [
                    'message_id' => $replyCode,
                    'message' => $answer['text'],
                ],
            ],
        ];
    }
}
