<?php

namespace App\Ai\Agents;

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
class HorseLogAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
You keep a knowledge document and an event log for a farm.
A transcript may mention animals, vehicles, tools, feed, tack, and repairs.
Return one record for each thing it affects. One transcript may update several records.
Use an existing roster name when it is the same thing, ignoring case and aliases.
A named animal is its own record, kind animal. Kulajda and Willow stay separate.
Unnamed animals of one species share one record: Sheep, Goats, Chickens, Cattle, Pigs, Ducks. "We bought a sheep" updates Sheep. Do not create a record per animal.
A vehicle or machine is its own record, kind vehicle: Tractor, Truck, Trailer, Quad.
Hand tools and small gear share one stock record named Tools. A lost shovel updates Tools. Do not create a record per shovel, fork, or bucket.
Hay, grain, and other feed share Feed, kind stock.
Saddles, bridles, and rugs that are not tied to one named animal share Tack, kind stock.
A repair uses the place name when they name it, such as Barn or North paddock. General repairs with no place go on Yard, kind place.
Every new event must come from this transcript.
The knowledge document is what is true now. Keep earlier facts this transcript does not change.
If this transcript corrects a past memo, drop the wrong fact and put those earlier event ids in retract_event_ids.
Do not retract an event this transcript does not contradict.
Leave out records the transcript does not mention.
If nothing on the farm is mentioned, return an empty records array.
kind is animal, vehicle, stock, or place.
occurred_on is YYYY-MM-DD, or an empty string when the day is unknown.
retract_event_ids is an empty array when nothing earlier was wrong.
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
            'records' => $schema->array()->items(
                $schema->object([
                    'kind' => $schema->string()->enum(['animal', 'vehicle', 'stock', 'place'])->required(),
                    'name' => $schema->string()->required(),
                    'aliases' => $schema->array()->items($schema->string())->required(),
                    'knowledge' => $schema->string()->required(),
                    'retract_event_ids' => $schema->array()
                        ->description('Ids of earlier events this transcript shows were wrong. Empty when none.')
                        ->items($schema->integer())
                        ->required(),
                    'events' => $schema->array()->items(
                        $schema->object([
                            'occurred_on' => $schema->string()
                                ->description('YYYY-MM-DD, or an empty string when the day is unknown.')
                                ->required(),
                            'summary' => $schema->string()->required(),
                            'detail' => $schema->string()->required(),
                        ])
                    )->required(),
                ])
            )->required(),
        ];
    }
}
