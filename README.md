# Parcely Jičín

## Co aplikace dělá

Webová aplikace zobrazuje katastrální parcely na mapě. Data se načítají žívě
ze služby WFS ČÚZK pro právě viditelnou oblast mapy, od přiblížení 18. Po
kliknuti na parcelu se zobrazí její čislo, výměra a katastrální reference.
Mapa se otevře na centru Jičína.

## Požadavky

- PHP 8.1 nebo novější s rozšířením SimpleXML (běžná součást instalace)
- povolené `allow_url_fopen` (výchozí nastavení) a připojení k internetu,
  protože se data stahují z ČÚZK
- Composer ani jiné závisloti nejsou potřeba
- Node.js je potřeba jen při vývojí frontendu, hotová aplikace je v `public/app/`

## Spuštění

V kořenovém adresáři projektu:
php -S localhost:8000 -t public

Poté otevřete http://localhost:8000/ (přesměruje na aplikaci). Pokud je port obsazény,
použijte jiný, napřiklad `php -S localhost:8081 -t public`.

## Struktura projektu

```
.
├── public/
│   ├── index.php          # autoloader a směrování
│   ├── app/               # sestavená aplikace z web/
│   └── map.html           # první verze mapy bez sestavení, záložní kopie
├── src/
│   ├── JsonResponse.php   # tvorba JSON odpovědí
│   ├── Bbox.php           # kontrola a limity oblasti mapy
│   ├── CuzkClient.php     # dotazy na WFS ČÚZK
│   ├── GmlParser.php      # převod GML do GeoJSON
│   └── ParcelController.php # obsluha endpointu
├── web/                   # zdrojový frontend: React, Vite a TypeScript
└── screenshots/           # snímky obrazovky aplikace
```

## API

Endpoint `GET /api/parcels?bbox=minLon,minLat,maxLon,maxLat` vrací při úspěchu
(`200`) GeoJSON `FeatureCollection`. Každá parcela má v `properties` položky
`label`, `area` a `reference`.

- `400` – neplatný nebo příliš velký bbox: plocha nejvýše `0,000101 °²`,
  každá strana nejvýše `0,03 °`
- `502` – služba ČÚZK není dostupná nebo vrátila neplatná data
- `404` – neznámá adresa

Příklad požadavku:

```sh
curl "http://localhost:8000/api/parcels?bbox=15.350,50.436,15.353,50.438"
```

## Rozhodnutí a proč

## Výkon

## Pokrytí území

Aplikace není omezená na okres: endpoint vrací parcely pro libovolnou oblast
v České republice, kterou ČÚZK poskytuje. Celý okres Jičín je tedy dostupný
posouváním mapy při přiblížení 18 a více; vždy se načítá jen viditelná oblast.

Ověřil jsem tato katastrální území:

| k. ú.          | kód    |
| -------------- | ------ |
| Jičín          | 659541 |
| Nová Paka      | 705128 |
| Lázně Bělohrad | 679330 |
| Sobotka        | 752096 |
| Libáň          | 681679 |

Při menším přiblížení se parcely nezobrazují (viz Rozhodnutí a Výkon).

## Omezení a co bych řešil jinak

## Vývoj frontendu

## Strávený čas

Úkol mi zabral přibližně 10 hodin čisté práce, rozložených do tří dní
(28. až 30. 10.), protože jsem na něm pracoval po práci a ve volném čase.
Zhruba třetinu času zabralo učení PHP od základů (PHP jsem předtím neznal)
a zjišťování, jak se chová WFS služba ČÚZK; zbytek byl backend, frontend a
měření výkonu.
