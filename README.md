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

- **Žíva data z WFS ČÚZK**, ne předem stažená: aplikace nepotřebuje databází a data jsou aktualní. Nevýhodou je zavislost na cizí službě, proto endpoint při selhání vrací `502`.
- **GML v EPSG:4326**: GeoJSON z ČÚZK neobsahuje atributy parcel (čislo, výměru), GML ano. EPSG:4326 odpadá převod ze S-JTSK.
- **PHP jako vrstva mezi ČÚZK a prohližečem**: zpracuje GML, prohodí osy (ČÚZK používá lat, lon GeoJSON a Leaflet lon, lat), složí GeoJSON a vnitřní hranice parcel (díry). Prohlížeč tak dostává malou odpoveď.
- **Vlatsní API přijimá bbox jako `minLon,minLat,maxLon,maxLat`** (běžné pro mapy); prohození os pro ČÚZK dělá až `Bbox::toCzukParam()`.
- **Limit oblasti podle plochy** (nejvýše 0.000101 °², strana nejvýše 0,03 °): zátěž závisi na počtu parcel, ne na délce strany. Hodnotu jsem určil měřením (viz Výkon).
- **Parcely se načítají od přiblížení 18**, jen pro viditelnou oblast, s prodlevou 250 ms po zastavení mapy a se zrušením zastaralých požadavků (`AbortController`), aby mapa zůstala plynulá.
- **Čisté PHP bez frameworku**: Frameworky jsem nepoužíval jelikož s níma ani s PHP zatim moc zkušeností nemam, proto radší jsem ponechal čisty PHP.
- **Frontend v Reactu (Vite, TypeScript, Leaflet)**. Sestavená aplikace je v
  `public/app/`, takže ke spuštění stačí PHP. Starší verze mapy bez sestavení
  (`public/map.html`) zůstala jako záloha.

## Výkon

Měříl jsem odezvu endpointu `/api/parcels` pro různě velké oblasti v centru Jíčína (3 opakovaní, `curl -w "%{time_total}"`):

| Oblast                              | Čas            | Velikost odpovědi |
| ----------------------------------- | -------------- | ----------------- |
| 0,005 ° × 0,005 °                   | 0,23 až 0,33 s | 155 KB            |
| 0,010 ° × 0,010 ° (asi 1800 parcel) | 1,26 až 1,72 s | 772 KB            |
| 0,015 ° × 0,015 °                   | 1,64 až 2,01 s | 1,99 MB           |

Čas jsem rozdělil na stažení z ČÚZK a zpracovaní v PHP (jedno měření):
stažení trvalo 0,36 až 2,14 s, zpracovaní v PHP jen 0,04 až 0,08 s. Hlavní brzdou je tedy služba ČÚZK, ne PHP. Proto jsem omezil velikost oblasti a odpoveď se nenačitá při menším přibližení.

GeoJSON, který posílám prohližečí, je asi 3,8 krát menší než GML z ČÚZK
(97 KB na proti 369 KB pro stejnou oblast s 159 parcelami).

Velká oblast (kolem 0,07 ° × 0,07 °) shodila PHP kvůli limitu paměti 128 MB, protože SimpleXML načítá celé XML najednou. Proto má `Bbox` limit na plochu.

Čisla jsou orientační: jedno měření v centru města, kde jsou parcely nejhustší, a odezva ČÚZK kolisá.

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

- Nemám cache opovědí ČÚZK, každy pohyb mapy znamená novy dotaz. Cache v `CuzkClient` by zrychlila hlavně opakovanné oblasti.
- Při menším přibližení než 18 se parcely nezobrazují. Lepši by bylo ukázat alespoň hranice katastrálních území.
- SimpleXML načitá celé XML do paměti; pro velké oblasti bych použil proudové čtení (`XMLReader`)
- Frontend nekontroluje tvar odpovědi API za běhu (jen TypeScript typy).
- Chybí automatické testy (například pro `Bbox` a `GmlParser`).

## Vývoj frontendu

Zdrojový kód je ve složce `web/` (React, Vite, TypeScript). Hotová aplikace je
už sestavená v `public/app/`, takže pro samotné spuštění Node.js není potřeba.

    cd web
    npm install
    npm run dev       # http://localhost:5173/app/, /api se přeposílá na PHP (port 8000)
    npm run build     # sestaví aplikaci do ../public/app

## Strávený čas

Úkol mi zabral přibližně 10 hodin čisté práce, rozložených do tří dní
(28. až 30. 10.), protože jsem na něm pracoval po práci a ve volném čase.
Zhruba třetinu času zabralo učení PHP od základů (PHP jsem předtím neznal)
a zjišťování, jak se chová WFS služba ČÚZK; zbytek byl backend, frontend a
měření výkonu.
