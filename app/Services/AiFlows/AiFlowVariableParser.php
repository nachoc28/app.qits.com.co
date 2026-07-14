<?php

namespace App\Services\AiFlows;

class AiFlowVariableParser
{
    private const VARIABLE_PATTERN = '/\{\{(.*?)\}\}/s';
    private const VALID_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @return array{variables: array<int, string>, invalid_tokens: array<int, string>}
     */
    public function parse(?string $prompt): array
    {
        $prompt = (string) $prompt;

        if ($prompt === '') {
            return [
                'variables' => [],
                'invalid_tokens' => [],
            ];
        }

        preg_match_all(self::VARIABLE_PATTERN, $prompt, $matches);

        $variables = [];
        $invalidTokens = [];

        foreach ($matches[1] ?? [] as $rawToken) {
            $token = trim((string) $rawToken);

            if ($token === '' || ! preg_match(self::VALID_NAME_PATTERN, $token)) {
                if (! in_array($token, $invalidTokens, true)) {
                    $invalidTokens[] = $token;
                }

                continue;
            }

            if (! in_array($token, $variables, true)) {
                $variables[] = $token;
            }
        }

        return [
            'variables' => $variables,
            'invalid_tokens' => $invalidTokens,
        ];
    }
}
