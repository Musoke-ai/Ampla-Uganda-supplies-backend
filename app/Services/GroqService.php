<?php

namespace App\Services;

class GroqService
{
    protected string $apiKey;
    protected string $model;
    protected string $url = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY') ?? '';
        $this->model = env('GROQ_MODEL') ?: 'llama-3.1-8b-instant';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('GROQ_API_KEY is missing in .env');
        }
    }

    public function ask(string $userQuestion, array $tools): string
    {
        $client = \Config\Services::curlrequest();

        $systemPrompt = $this->buildSystemPrompt($tools);

        try {
            $response = $client->post($this->url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userQuestion,
                        ],
                    ],
                    'temperature' => 0.2,
                    'max_completion_tokens' => 1000,
                ],
                'timeout' => 120,
            ]);

            $result = json_decode($response->getBody(), true);

            return $result['choices'][0]['message']['content'] ?? '';

        } catch (\Throwable $e) {
            log_message('error', 'Groq API Error: ' . $e->getMessage());

            return json_encode([
                'intent' => 'error',
                'tool' => null,
                'arguments' => [],
                'answer' => 'The AI service failed to respond. Please try again.'
            ]);
        }
    }

    private function buildSystemPrompt(array $tools): string
    {
        $toolJson = json_encode($tools, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an AI assistant for an inventory, sales, customers, and production management system.

Your job is to understand the user's question and select the correct backend tool.

IMPORTANT RULES:
- Return ONLY valid JSON.
- Do not include markdown.
- Do not include explanations outside JSON.
- Do not invent database results.
- Do not calculate sensitive totals yourself.
- Select a tool only from the available tools.
- If no tool matches, return tool as null.

Available tools:
{$toolJson}

Return exactly this JSON structure:
{
  "intent": "query|report|analysis|unknown|error",
  "tool": "tool_name_or_null",
  "arguments": {},
  "answer": "short user-friendly message"
}
PROMPT;
    }
}