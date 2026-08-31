<?php
/**
 * Geometrie der schematischen Weltkarte.
 * Erzeugt von tools/gen_map.py - nicht von Hand nachpflegen.
 *
 * Nur Form und Lage. Name, Kontinent und Kartensymbol eines Gebiets
 * stehen in der Datenbank und kommen ueber WorldMap - sonst muesste man
 * jeden Namen an zwei Stellen pflegen.
 *
 * @return array{width:int, height:int, continents:array, sea:list,
 *               territories:array<int,array{d:string,cx:float,cy:float}>}
 */
return [
    'width'  => 1240,
    'height' => 700,
    'continents' => [
        1 => ['name' => 'Nordamerika', 'fill' => '#f0d9a8', 'x' => 232, 'y' => 30],
        2 => ['name' => 'Südamerika', 'fill' => '#e3b98f', 'x' => 334, 'y' => 690],
        3 => ['name' => 'Europa', 'fill' => '#c9d8ef', 'x' => 612, 'y' => 30],
        4 => ['name' => 'Afrika', 'fill' => '#e8cf9a', 'x' => 622, 'y' => 620],
        5 => ['name' => 'Asien', 'fill' => '#d7e7c8', 'x' => 945, 'y' => 30],
        6 => ['name' => 'Australien', 'fill' => '#e6c6d6', 'x' => 1086, 'y' => 690],
    ],
    'sea' => [
        [396.5, 74.7, 511.0, 114.0],  // Grönland - Island
        [379.6, 492.1, 573.3, 353.4],  // Brasilien - Nordafrika
        [701.5, 432.8, 803.0, 317.0],  // Ostafrika - Mittlerer Osten
        [1000.0, 403.0, 1024.0, 493.0],  // Siam - Indonesien
        [623.5, 248.8, 692.1, 337.9],  // Südeuropa - Ägypten
        [533.0, 250.0, 573.3, 353.4],  // Westeuropa - Nordafrika
        [701.5, 432.8, 725.0, 532.0],  // Ostafrika - Madagaskar
        [621.3, 546.2, 725.0, 532.0],  // Südafrika - Madagaskar
        [1093.0, 120.0, 1111.0, 236.0],  // Kamtschatka - Japan
        [973.0, 232.0, 1111.0, 236.0],  // Mongolei - Japan
        [228.7, 324.4, 301.8, 401.6],  // Mittelamerika - Venezuela
        [81.2, 101.5, 4, 101.5],  // Alaska - Kamtschatka (Kartenrand)
        [1093.0, 120.0, 1236, 120.0],  // Kamtschatka - Alaska (Kartenrand)
    ],
    'territories' => [
        1 => [
            'cx' => 81.2,
            'cy' => 101.5,
            'd'  => 'M30 72 L122 60 L132 132 L40 142 Z',
        ],  // Alaska
        2 => [
            'cx' => 201.5,
            'cy' => 96.1,
            'd'  => 'M138 60 L262 54 L266 132 L140 138 Z',
        ],  // Nordwest-Territorium
        3 => [
            'cx' => 396.5,
            'cy' => 74.7,
            'd'  => 'M352 28 L448 36 L436 122 L350 116 Z',
        ],  // Grönland
        4 => [
            'cx' => 193.5,
            'cy' => 175.0,
            'd'  => 'M140 144 L246 140 L246 208 L140 208 Z',
        ],  // Alberta
        5 => [
            'cx' => 294.2,
            'cy' => 172.5,
            'd'  => 'M252 138 L336 136 L336 208 L252 208 Z',
        ],  // Ontario
        6 => [
            'cx' => 383.0,
            'cy' => 170.0,
            'd'  => 'M342 130 L428 136 L422 208 L342 208 Z',
        ],  // Quebec
        7 => [
            'cx' => 210.0,
            'cy' => 249.8,
            'd'  => 'M142 212 L276 212 L276 288 L146 288 Z',
        ],  // Weststaaten
        8 => [
            'cx' => 332.7,
            'cy' => 250.0,
            'd'  => 'M282 212 L392 216 L376 288 L282 288 Z',
        ],  // Oststaaten
        9 => [
            'cx' => 228.7,
            'cy' => 324.4,
            'd'  => 'M152 292 L288 292 L302 356 L206 362 L160 336 Z',
        ],  // Mittelamerika
        10 => [
            'cx' => 301.8,
            'cy' => 401.6,
            'd'  => 'M246 372 L352 368 L358 432 L250 434 Z',
        ],  // Venezuela
        11 => [
            'cx' => 287.1,
            'cy' => 486.5,
            'd'  => 'M246 442 L322 440 L328 532 L252 532 Z',
        ],  // Peru
        12 => [
            'cx' => 379.6,
            'cy' => 492.1,
            'd'  => 'M332 438 L428 444 L422 548 L336 542 Z',
        ],  // Brasilien
        13 => [
            'cx' => 306.6,
            'cy' => 599.1,
            'd'  => 'M266 542 L348 548 L336 662 L276 656 Z',
        ],  // Argentinien
        14 => [
            'cx' => 511.0,
            'cy' => 114.0,
            'd'  => 'M480 90 L538 88 L542 138 L484 140 Z',
        ],  // Island
        15 => [
            'cx' => 601.0,
            'cy' => 84.0,
            'd'  => 'M560 46 L636 42 L642 122 L566 126 Z',
        ],  // Skandinavien
        16 => [
            'cx' => 512.5,
            'cy' => 178.2,
            'd'  => 'M480 150 L540 150 L546 206 L484 206 Z',
        ],  // Großbritannien
        17 => [
            'cx' => 603.0,
            'cy' => 170.0,
            'd'  => 'M560 134 L642 132 L646 206 L564 208 Z',
        ],  // Nordeuropa
        18 => [
            'cx' => 698.7,
            'cy' => 132.4,
            'd'  => 'M652 58 L748 64 L742 206 L654 206 Z',
        ],  // Ukraine
        19 => [
            'cx' => 533.0,
            'cy' => 250.0,
            'd'  => 'M490 214 L572 212 L576 286 L494 288 Z',
        ],  // Westeuropa
        20 => [
            'cx' => 623.5,
            'cy' => 248.8,
            'd'  => 'M582 214 L662 212 L664 284 L586 286 Z',
        ],  // Südeuropa
        21 => [
            'cx' => 573.3,
            'cy' => 353.4,
            'd'  => 'M500 300 L642 296 L646 406 L506 412 Z',
        ],  // Nordafrika
        22 => [
            'cx' => 692.1,
            'cy' => 337.9,
            'd'  => 'M650 298 L732 300 L734 376 L654 378 Z',
        ],  // Ägypten
        23 => [
            'cx' => 701.5,
            'cy' => 432.8,
            'd'  => 'M656 384 L742 388 L746 482 L662 478 Z',
        ],  // Ostafrika
        24 => [
            'cx' => 606.5,
            'cy' => 457.9,
            'd'  => 'M560 420 L650 418 L652 496 L564 498 Z',
        ],  // Kongo
        25 => [
            'cx' => 621.3,
            'cy' => 546.2,
            'd'  => 'M576 506 L666 504 L656 592 L586 590 Z',
        ],  // Südafrika
        26 => [
            'cx' => 725.0,
            'cy' => 532.0,
            'd'  => 'M700 496 L746 500 L750 568 L704 564 Z',
        ],  // Madagaskar
        27 => [
            'cx' => 797.5,
            'cy' => 117.2,
            'd'  => 'M756 58 L836 58 L840 176 L758 176 Z',
        ],  // Ural
        28 => [
            'cx' => 893.1,
            'cy' => 112.5,
            'd'  => 'M846 50 L936 48 L940 176 L850 176 Z',
        ],  // Sibirien
        29 => [
            'cx' => 994.6,
            'cy' => 83.0,
            'd'  => 'M946 44 L1042 48 L1044 120 L948 120 Z',
        ],  // Jakutien
        30 => [
            'cx' => 1093.0,
            'cy' => 120.0,
            'd'  => 'M1050 50 L1132 52 L1136 190 L1054 188 Z',
        ],  // Kamtschatka
        31 => [
            'cx' => 995.0,
            'cy' => 160.0,
            'd'  => 'M946 128 L1042 128 L1044 192 L948 192 Z',
        ],  // Irkutsk
        32 => [
            'cx' => 973.0,
            'cy' => 232.0,
            'd'  => 'M900 198 L1042 198 L1046 266 L904 266 Z',
        ],  // Mongolei
        33 => [
            'cx' => 1111.0,
            'cy' => 236.0,
            'd'  => 'M1076 200 L1142 202 L1146 272 L1080 270 Z',
        ],  // Japan
        34 => [
            'cx' => 806.0,
            'cy' => 225.0,
            'd'  => 'M758 184 L852 184 L854 266 L760 266 Z',
        ],  // Afghanistan
        35 => [
            'cx' => 935.0,
            'cy' => 315.0,
            'd'  => 'M862 274 L1004 272 L1008 356 L866 358 Z',
        ],  // China
        36 => [
            'cx' => 803.0,
            'cy' => 317.0,
            'd'  => 'M752 274 L852 274 L854 360 L754 360 Z',
        ],  // Mittlerer Osten
        37 => [
            'cx' => 906.7,
            'cy' => 410.6,
            'd'  => 'M862 366 L952 366 L946 458 L866 456 Z',
        ],  // Indien
        38 => [
            'cx' => 1000.0,
            'cy' => 403.0,
            'd'  => 'M960 366 L1036 368 L1040 440 L964 438 Z',
        ],  // Siam
        39 => [
            'cx' => 1024.0,
            'cy' => 493.0,
            'd'  => 'M984 456 L1060 458 L1064 530 L988 528 Z',
        ],  // Indonesien
        40 => [
            'cx' => 1114.2,
            'cy' => 486.5,
            'd'  => 'M1074 452 L1150 454 L1154 522 L1078 518 Z',
        ],  // Neuguinea
        41 => [
            'cx' => 1042.0,
            'cy' => 593.0,
            'd'  => 'M1000 546 L1080 548 L1084 640 L1004 638 Z',
        ],  // Westaustralien
        42 => [
            'cx' => 1132.0,
            'cy' => 594.0,
            'd'  => 'M1090 542 L1170 544 L1174 646 L1094 644 Z',
        ],  // Ostaustralien
    ],
];
