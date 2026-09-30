<?php

namespace App\Ai\Agents;

use App\Services\QuestionAnswerer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Groq)]
#[UseCheapestModel]
#[Strict]
#[MaxTokens(4096)]
class QuestionAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public string $language = 'en';

    public function instructions(): string
    {
        $unknown = QuestionAnswerer::unknown($this->language);

        return <<<TEXT
You answer questions a person asked out loud in a farm voice memo.
Return one item for each question they asked. A statement is not a question.
Leave the list empty when they did not ask anything.
Answer only from the roster knowledge and events in the prompt.
Do not use general knowledge.
Write each answer in the stable language named in the prompt.
Keep the question in the words the speaker used.
If the records do not contain the answer, set answer to exactly: {$unknown}
Keep each answer to a few sentences of plain text.
TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== Lab::Groq && $provider !== Lab::Groq->value) {
            return [];
        }

        return [
            'reasoning_effort' => 'low',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items(
                $schema->object([
                    'question' => $schema->string()->required(),
                    'answer' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
