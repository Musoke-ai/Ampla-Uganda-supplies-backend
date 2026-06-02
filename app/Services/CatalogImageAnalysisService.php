<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

class CatalogImageAnalysisService
{
    private string $apiKey;
    private string $model;
    private string $url = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = (string) env('GROQ_API_KEY', '');
        $this->model = (string) env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct');
    }

    public function analyze(UploadedFile $image, string $type, array $categories = []): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('GROQ_API_KEY is missing in .env');
        }

        $path = $image->getTempName();
        $mime = $image->getMimeType() ?: 'image/jpeg';
        $base64 = base64_encode((string) file_get_contents($path));
        $prompt = $this->buildPrompt($type, $categories);

        $client = \Config\Services::curlrequest();
        $response = $client->post($this->url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract inventory catalog fields from product and raw material images. Return strict JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mime . ';base64,' . $base64,
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.1,
                'max_completion_tokens' => 700,
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 120,
        ]);

        $payload = json_decode($response->getBody(), true);
        $content = $payload['choices'][0]['message']['content'] ?? '{}';
        $decoded = $this->decodeJsonObject((string) $content);
        $fields = is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
        $suggestedCategoryName = $this->cleanText(
            $decoded['suggestedCategoryName'] ?? ($fields['category'] ?? ($fields['itemCategory'] ?? ''))
        );
        $matchedCategory = $this->matchCategory($suggestedCategoryName, $categories);

        return [
            'status' => true,
            'type' => $type,
            'fields' => $this->sanitizeFields($fields),
            'suggestedCategoryName' => $suggestedCategoryName,
            'suggestedCategoryId' => $matchedCategory['categoryId'] ?? null,
            'matchedCategoryName' => $matchedCategory['categoryName'] ?? null,
            'confidence' => $this->clampConfidence($decoded['confidence'] ?? null),
            'notes' => $this->cleanText($decoded['notes'] ?? '', 220),
            'shouldCreateCategory' => $suggestedCategoryName !== '' && empty($matchedCategory),
        ];
    }

    private function buildPrompt(string $type, array $categories): string
    {
        $categoryNames = array_values(array_filter(array_map(
            static fn ($category) => (string) ($category['categoryName'] ?? ''),
            $categories
        )));
        $categoryText = empty($categoryNames) ? 'No categories exist yet.' : implode(', ', $categoryNames);

        if ($type === 'raw_material') {
            return <<<PROMPT
Look at this raw material image and extract catalog fields for a production/raw materials form.

Existing categories: {$categoryText}

Return exactly this JSON shape:
{
  "fields": {
    "name": "",
    "description": "",
    "materialCode": "",
    "rawMaterialBarcode": "",
    "category": "",
    "size": "",
    "unitOfMeasure": "",
    "supplier": "",
    "storageLocation": "",
    "note": ""
  },
  "suggestedCategoryName": "",
  "confidence": 0,
  "notes": ""
}

Rules:
- Use visible label text, packaging text, barcode text, and clear product identity.
- Fill description with a short useful description from visible text; if no description is printed, write one based on what the image clearly shows.
- Do not invent quantity, unit price, supplier, expiry, or storage location unless clearly visible.
- Choose suggestedCategoryName from existing categories when one fits; otherwise propose a short new category name.
- Keep values short and practical for inventory entry.
PROMPT;
        }

        return <<<PROMPT
Look at this product image and extract catalog fields for a sellable inventory product form.

Existing categories: {$categoryText}

Return exactly this JSON shape:
{
  "fields": {
    "itemName": "",
    "itemDescription": "",
    "itemBrand": "",
    "itemModel": "",
    "itemSku": "",
    "itemBarcode": "",
    "itemUnit": "",
    "itemQuality": "",
    "itemCondition": "",
    "itemSize": "",
    "itemNotes": ""
  },
  "suggestedCategoryName": "",
  "confidence": 0,
  "notes": ""
}

Rules:
- Use visible label text, packaging text, barcode text, brand, model, and obvious product identity.
- Fill itemDescription with a short useful description from visible text; if no description is printed, write one based on what the image clearly shows.
- Do not invent stock quantity or prices unless printed on the item.
- Choose suggestedCategoryName from existing categories when one fits; otherwise propose a short new category name.
- Keep values short and practical for inventory entry.
PROMPT;
    }

    private function decodeJsonObject(string $content): array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function sanitizeFields(array $fields): array
    {
        $clean = [];

        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $clean[$key] = $this->cleanText($value);
        }

        return $clean;
    }

    private function matchCategory(string $suggestedCategoryName, array $categories): array
    {
        $needle = mb_strtolower(trim($suggestedCategoryName));
        if ($needle === '') {
            return [];
        }

        $best = [];
        $bestScore = 0;

        foreach ($categories as $category) {
            $name = (string) ($category['categoryName'] ?? '');
            $normalized = mb_strtolower($name);
            if ($normalized === '') {
                continue;
            }

            if ($normalized === $needle) {
                return $category;
            }

            similar_text($needle, $normalized, $percent);
            if (str_contains($normalized, $needle) || str_contains($needle, $normalized)) {
                $percent += 18;
            }

            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $category;
            }
        }

        return $bestScore >= 62 ? $best : [];
    }

    private function cleanText($value, int $maxLength = 120): string
    {
        $cleaned = strip_tags((string) ($value ?? ''));
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned) ?? '';
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if ($cleaned === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($cleaned, 0, $maxLength) : substr($cleaned, 0, $maxLength);
    }

    private function clampConfidence($value): int
    {
        $confidence = (int) round((float) ($value ?? 0));

        return max(0, min(100, $confidence));
    }
}
