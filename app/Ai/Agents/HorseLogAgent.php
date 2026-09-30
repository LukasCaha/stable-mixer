<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::Groq)]
#[UseCheapestModel]
class HorseLogAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
You keep a knowledge document and an event log for each horse in one stable.
Read the transcript and return every horse it mentions.
Use a horse's existing name from the roster when it is the same animal, ignoring case and aliases.
Create a horse only when the transcript names an animal that is not on the roster.
One transcript may mention many horses and many events.
Every new event must come from this transcript.
The knowledge document is what is true now. Keep earlier facts this transcript does not change.
If this transcript corrects a past memo, drop the wrong fact from the knowledge document and put those earlier event ids in retract_event_ids.
Do not retract an event this transcript does not contradict.
Leave out horses the transcript does not mention.
If the transcript mentions no horse, return an empty horses array.
TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'horses' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'aliases' => $schema->array()->items($schema->string())->required(),
                    'knowledge' => $schema->string()->required(),
                    'retract_event_ids' => $schema->array()->items($schema->integer())->required(),
                    'events' => $schema->array()->items(
                        $schema->object([
                            'occurred_on' => $schema->string()->nullable()->required(),
                            'summary' => $schema->string()->required(),
                            'detail' => $schema->string()->required(),
                        ])
                    )->required(),
                ])
            )->required(),
        ];
    }
}
