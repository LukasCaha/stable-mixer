<?php

namespace App\Support;

use App\Enums\SubjectKind;

class RecordScope
{
    /**
     * Fungible hand tools share one stock record.
     *
     * @var array<string, true>
     */
    private const TOOLS = [
        'shovel' => true,
        'spade' => true,
        'fork' => true,
        'pitchfork' => true,
        'rake' => true,
        'broom' => true,
        'bucket' => true,
        'wheelbarrow' => true,
        'hammer' => true,
        'hose' => true,
        'ladder' => true,
        'tool' => true,
        'tools' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const FEED = [
        'hay' => true,
        'grain' => true,
        'feed' => true,
        'oats' => true,
        'chaff' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const TACK = [
        'saddle' => true,
        'bridle' => true,
        'halter' => true,
        'rug' => true,
        'tack' => true,
    ];

    /**
     * Unnamed animals of one species share one record.
     *
     * @var array<string, string>
     */
    private const SPECIES = [
        'sheep' => 'Sheep',
        'lamb' => 'Sheep',
        'ewe' => 'Sheep',
        'ram' => 'Sheep',
        'goat' => 'Goats',
        'kid' => 'Goats',
        'chicken' => 'Chickens',
        'hen' => 'Chickens',
        'rooster' => 'Chickens',
        'chick' => 'Chickens',
        'cow' => 'Cattle',
        'cattle' => 'Cattle',
        'calf' => 'Cattle',
        'bull' => 'Cattle',
        'pig' => 'Pigs',
        'hog' => 'Pigs',
        'duck' => 'Ducks',
    ];

    /**
     * @var array<string, string>
     */
    private const VEHICLES = [
        'tractor' => 'Tractor',
        'truck' => 'Truck',
        'trailer' => 'Trailer',
        'quad' => 'Quad',
        'atv' => 'Quad',
        'gator' => 'Gator',
        'ute' => 'Truck',
        'loader' => 'Loader',
        'mower' => 'Mower',
    ];

    /**
     * @var array<string, string>
     */
    private const PLACES = [
        'barn' => 'Barn',
        'stable' => 'Barn',
        'yard' => 'Yard',
        'arena' => 'Arena',
        'paddock' => 'Paddocks',
        'fence' => 'Fences',
        'fences' => 'Fences',
        'gate' => 'Fences',
        'repair' => 'Yard',
        'repairs' => 'Yard',
    ];

    /**
     * @return array{0: SubjectKind, 1: string}
     */
    public static function resolve(string $kind, string $name): array
    {
        $bare = preg_replace('/^(the|a|an)\s+/u', '', mb_strtolower(trim($name))) ?? '';
        $bare = trim($bare);

        if (isset(self::TOOLS[$bare])) {
            return [SubjectKind::Stock, 'Tools'];
        }

        if (isset(self::FEED[$bare])) {
            return [SubjectKind::Stock, 'Feed'];
        }

        if (isset(self::TACK[$bare])) {
            return [SubjectKind::Stock, 'Tack'];
        }

        if (isset(self::SPECIES[$bare])) {
            return [SubjectKind::Animal, self::SPECIES[$bare]];
        }

        if (isset(self::VEHICLES[$bare])) {
            return [SubjectKind::Vehicle, self::VEHICLES[$bare]];
        }

        if (isset(self::PLACES[$bare])) {
            return [SubjectKind::Place, self::PLACES[$bare]];
        }

        return [SubjectKind::tryFrom($kind) ?? SubjectKind::Stock, trim($name)];
    }
}
