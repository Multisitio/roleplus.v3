<?php
/**
 */
class _openai
{
    /*curl https://api.openai.com/v1/completions \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer " \
    -d '{"model": "text-davinci-003", "prompt": "Say this is a test", "temperature": 0, "max_tokens": 7}'*/
    #
    static public function ask($prompt, $role='', $name='')
    {
        ini_set('max_execution_time', 120000);

        $url = 'https://api.openai.com/v1/chat/completions';

        /*$data = [
            'model' => 'text-davinci-003',
            'prompt' => "$role: $prompt",
            'temperature' => 0,
            'max_tokens' => 1900
        ];*/

        /*$data = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $role ?? 'You are a helpful assistant.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0,
            'max_tokens' => 1900
        ];*/

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $role ? "$role: $prompt" : $prompt
                ]
            ],
            'temperature' => 0,
            'max_tokens' => 1900
        ];

        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $key = Config::get("keys.openai.token");
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer ' . $key;
        $headers[] = 'OpenAI-Beta: assistants=v2';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $result .= 'Error:' . curl_error($ch);
        }
        curl_close($ch);
		_mail::toAdmin('_openai::ask', '<pre>'.print_r($result, 1));
        return json_decode($result);
    }
}
