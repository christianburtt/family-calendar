<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Generates and normalises the human-friendly codes used to join a calendar.
 *
 * A code is three lowercase English words joined by hyphens, for example
 * "brave-otter-teapot". The word lists are deliberately short and concrete so
 * the code is easy to read aloud, spell and type on a phone.
 */
final class JoinCode
{
    /**
     * Words used for the first segment of a code.
     *
     * @var list<string>
     */
    public const array ADJECTIVES = [
        'able', 'active', 'agile', 'airy', 'amber', 'ample', 'ancient', 'arctic',
        'artsy', 'astute', 'autumn', 'azure', 'balmy', 'beige', 'blithe', 'blue',
        'bold', 'brainy', 'brave', 'breezy', 'bright', 'brisk', 'broad', 'bronze',
        'brown', 'burly', 'calm', 'candid', 'caring', 'cerise', 'chalky', 'cheerful',
        'chilly', 'civic', 'clever', 'cloudy', 'coastal', 'cobalt', 'cold', 'comic',
        'cool', 'copper', 'coral', 'cozy', 'crafty', 'crisp', 'curious', 'curly',
        'dainty', 'daring', 'dapper', 'dewy', 'diligent', 'dreamy', 'dusty', 'eager',
        'early', 'earthy', 'easy', 'elegant', 'emerald', 'epic', 'fair', 'fancy',
        'fearless', 'feisty', 'fern', 'fiery', 'fleet', 'floral', 'fluffy', 'foggy',
        'fond', 'forest', 'frank', 'free', 'fresh', 'friendly', 'frosty', 'frugal',
        'funny', 'fuzzy', 'gallant', 'gentle', 'giddy', 'ginger', 'glad', 'gleeful',
        'glossy', 'golden', 'graceful', 'grand', 'grassy', 'grateful', 'green', 'grinning',
        'happy', 'hardy', 'hazel', 'hearty', 'helpful', 'hidden', 'honest', 'honey',
        'hopeful', 'humble', 'icy', 'indigo', 'inland', 'ivory', 'jade', 'jolly',
        'jovial', 'joyful', 'keen', 'kind', 'lambent', 'lavender', 'leafy', 'lemon',
        'light', 'lilac', 'lime', 'linen', 'lively', 'lucky', 'lunar', 'lush',
        'magenta', 'mellow', 'merry', 'mighty', 'mild', 'misty', 'modest', 'mossy',
        'muted', 'navy', 'neat', 'nimble', 'noble', 'nordic', 'novel', 'olive',
        'opal', 'orange', 'ornate', 'outgoing', 'pastel', 'patient', 'peach', 'peaceful',
        'pearl', 'peppery', 'perky', 'pine', 'pink', 'plain', 'playful', 'pleasant',
        'plum', 'plucky', 'polished', 'polite', 'powdery', 'pretty', 'prime', 'proud',
        'puffy', 'pumpkin', 'purple', 'quaint', 'quick', 'quiet', 'quirky', 'radiant',
        'rapid', 'rare', 'rosy', 'royal', 'ruby', 'rugged', 'russet', 'rustic',
        'sandy', 'sapphire', 'scarlet', 'scenic', 'serene', 'sharp', 'shiny', 'shy',
        'silver', 'simple', 'sincere', 'sky', 'sleek', 'smart', 'smiling', 'smooth',
        'snowy', 'soft', 'solar', 'solid', 'sparkly', 'speedy', 'spicy', 'spiral',
        'spring', 'spruce', 'spry', 'steady', 'steel', 'sterling', 'still', 'stone',
        'stormy', 'sturdy', 'sunny', 'super', 'supple', 'swift', 'tan', 'teal',
        'tender', 'thrifty', 'tidy', 'tiny', 'toffee', 'topaz', 'tranquil', 'true',
        'tulip', 'umber', 'upbeat', 'urban', 'valiant', 'velvet', 'verdant', 'vibrant',
        'violet', 'vivid', 'warm', 'wavy', 'wheat', 'white', 'wide', 'wild',
        'windy', 'winter', 'wise', 'witty', 'woody', 'woolly', 'yellow', 'young',
        'zesty', 'zippy',
    ];

    /**
     * Words used for the second and third segments of a code.
     *
     * @var list<string>
     */
    public const array NOUNS = [
        'otter', 'teapot', 'lantern', 'badger', 'beacon', 'beetle', 'biscuit', 'bison',
        'blanket', 'boat', 'bobcat', 'bonfire', 'bottle', 'bramble', 'bread', 'breeze',
        'bridge', 'brook', 'bucket', 'buffalo', 'bugle', 'bunny', 'burrow', 'butter',
        'button', 'cabin', 'cactus', 'camel', 'candle', 'canoe', 'canyon', 'carpet',
        'carrot', 'castle', 'cedar', 'cello', 'chalet', 'cherry', 'chess', 'chestnut',
        'chicken', 'chimney', 'chinchilla', 'chisel', 'cider', 'cinnamon', 'circus', 'cliff',
        'clover', 'coast', 'cobweb', 'coconut', 'comet', 'compass', 'cookie', 'cottage',
        'cotton', 'cougar', 'coyote', 'crab', 'crane', 'crayon', 'creek', 'cricket',
        'crow', 'crystal', 'cupcake', 'cushion', 'daisy', 'dandelion', 'deer', 'desert',
        'diamond', 'dingo', 'dolphin', 'donkey', 'donut', 'dragon', 'drum', 'dune',
        'eagle', 'easel', 'ember', 'falcon', 'feather', 'fence', 'ferret', 'fiddle',
        'fig', 'finch', 'firefly', 'fjord', 'flamingo', 'flute', 'fountain', 'fox',
        'frog', 'galaxy', 'garden', 'garland', 'gecko', 'geyser', 'giraffe', 'glacier',
        'globe', 'glove', 'goldfish', 'goose', 'gopher', 'granite', 'grape', 'grass',
        'gravel', 'guitar', 'gull', 'hammer', 'hamster', 'harbor', 'harp', 'hatchet',
        'hawk', 'hedgehog', 'helmet', 'heron', 'hill', 'hippo', 'hive', 'horizon',
        'horse', 'hummingbird', 'iceberg', 'iris', 'island', 'ivy', 'jackal', 'jaguar',
        'jasmine', 'jelly', 'jewel', 'jungle', 'juniper', 'kayak', 'kettle', 'kiwi',
        'koala', 'kite', 'kitten', 'ladder', 'lagoon', 'lake', 'lamb', 'lamp',
        'laptop', 'lark', 'leaf', 'leopard', 'library', 'lighthouse', 'lily', 'lion',
        'lizard', 'lobster', 'locket', 'lotus', 'lynx', 'magnet', 'mammoth', 'mango',
        'maple', 'marble', 'marlin', 'meadow', 'melon', 'mesa', 'meteor', 'mitten',
        'mole', 'mongoose', 'monkey', 'moose', 'moth', 'mountain', 'mouse', 'muffin',
        'mule', 'mushroom', 'mustang', 'needle', 'nest', 'nickel', 'nightjar', 'noodle',
        'notebook', 'nutmeg', 'oak', 'oasis', 'ocean', 'onion', 'orchid', 'owl',
        'oyster', 'paddle', 'panda', 'pansy', 'panther', 'paper', 'parrot', 'pasta',
        'peanut', 'pear', 'pebble', 'pelican', 'pencil', 'penguin', 'pepper', 'petal',
        'phoenix', 'piano', 'pigeon', 'pillow', 'pineapple', 'planet', 'pocket', 'pond',
        'pony', 'poppy', 'porcupine', 'potato', 'pretzel', 'prism', 'puffin', 'puzzle',
        'pylon', 'quail', 'quarry', 'quartz', 'quill', 'quilt', 'rabbit', 'raccoon',
        'radish', 'rainbow', 'raven', 'ribbon', 'river', 'robin', 'rocket', 'rose',
        'rosemary', 'rowan', 'rudder', 'saffron', 'sage', 'sail', 'salmon', 'sandal',
        'sapling', 'scarecrow', 'scarf', 'seagull', 'seashell', 'sesame', 'shadow', 'shark',
        'sheep', 'shell', 'ship', 'shrub', 'skate', 'sketch', 'skunk', 'sled',
        'sloth', 'snail', 'snake', 'snowflake', 'sparrow', 'spider', 'spool', 'sprig',
        'squash', 'squirrel', 'star', 'starfish', 'steeple', 'stork', 'storm', 'stream',
        'sugar', 'sunflower', 'swallow', 'swan', 'sweater', 'swing', 'sycamore', 'table',
        'tadpole', 'tambourine', 'tangerine', 'telescope', 'temple', 'thistle', 'thunder', 'tiger',
        'timber', 'toad', 'tomato', 'torch', 'tortoise', 'tower', 'train', 'trellis',
        'trumpet', 'tunnel', 'turtle', 'umbrella', 'valley', 'vanilla', 'vase', 'violin',
        'volcano', 'wagon', 'walnut', 'walrus', 'wasp', 'waterfall', 'wave', 'weasel',
        'whale', 'wheel', 'willow', 'window', 'wolf', 'wombat', 'woodpecker', 'wreath',
        'wren', 'yacht', 'yarn', 'zebra', 'zinnia',
    ];

    /**
     * Generate a new, lowercase, hyphenated three word code.
     */
    public static function generate(): string
    {
        return implode('-', [
            self::pick(self::ADJECTIVES),
            self::pick(self::NOUNS),
            self::pick(self::NOUNS),
        ]);
    }

    /**
     * Normalise a code typed by a person into its canonical form.
     *
     * Accepts "Brave Otter Teapot", "brave_otter_teapot", " BRAVE--OTTER--TEAPOT "
     * and a whole share link pasted by mistake.
     */
    public static function normalise(string $code): string
    {
        // A pasted link only matters for its last path segment.
        if (str_contains($code, '/')) {
            $segments = array_values(array_filter(
                explode('/', (string) strtok($code, '?#')),
            ));

            $code = $segments === [] ? '' : end($segments);
        }

        return (string) Str::of($code)
            ->lower()
            ->replaceMatches('/[^a-z]+/', '-')
            ->trim('-');
    }

    /**
     * Pick a random word from a list.
     *
     * @param  list<string>  $words
     */
    private static function pick(array $words): string
    {
        return $words[random_int(0, count($words) - 1)];
    }
}
