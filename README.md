# Parcely Jičín

## Co aplikace dělá

Webová aplikace zobrazuje katastrální parcely na mapě. Data se načítají živě
ze služby WFS ČÚZK pro právě viditelnou oblast mapy, od přiblížení 18. Po
kliknutí na parcelu se zobrazí její číslo, výměra a katastrální reference.
Mapa se otevře na centru Jičína.

## Požadavky

- PHP 8.1 nebo novější s rozšířením SimpleXML (běžná součást instalace)
- povolené `allow_url_fopen` (výchozí nastavení) a připojení k internetu,
  protože se data stahují z ČÚZK
- složka `cache/` se vytvoří automaticky, projekt ale musí mít právo zápisu
- Composer ani jiné závislosti nejsou potřeba
- Testováno v Chrome a Firefoxu
- Node.js je potřeba jen při vývoji frontendu, hotová aplikace je v `public/app/`

## Spuštění

V kořenovém adresáři projektu:

```sh
php -S localhost:8000 -t public
```

Poté otevřete http://localhost:8000/ (přesměruje na aplikaci). Pokud je port obsazený,
použijte jiný, například `php -S localhost:8081 -t public`.

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
├── cache/                 # vytváří se za běhu, není v Gitu
├── web/                   # zdrojový frontend: React, Vite a TypeScript
└── screenshots/           # snímky obrazovky aplikace
```

## API

Endpoint `GET /api/parcels?bbox=minLon,minLat,maxLon,maxLat` vrací při úspěchu
(`200`) GeoJSON `FeatureCollection`. Každá parcela má v `properties` položky
`label`, `area` a `reference`.

- `400`: neplatný nebo příliš velký bbox (plocha nejvýše `0,000101 °²`,
  každá strana nejvýše `0,03 °`)
- `502`: služba ČÚZK není dostupná a data nejsou v cache, nebo vrátila neplatná data
- `404`: neznámá adresa

  Příklad požadavku:

```sh
curl "http://localhost:8000/api/parcels?bbox=15.350,50.436,15.353,50.438"
```

## Rozhodnutí a proč

- Živá data z WFS ČÚZK, ne předem stažená: aplikace nepotřebuje databázi a data jsou aktuální. Nevýhodou je závislost na cizí službě; při výpadku bez dostupných dat v cache endpoint vrací `502`.
- GML v EPSG:4326: GeoJSON z ČÚZK neobsahuje atributy parcel (číslo, výměru), GML ano. EPSG:4326 odpadá převod ze S-JTSK.
- PHP jako vrstva mezi ČÚZK a prohlížečem: zpracuje GML, prohodí osy (ČÚZK používá pořadí lat, lon, kdežto GeoJSON a Leaflet lon, lat) a složí GeoJSON včetně vnitřních hranic parcel (děr). Prohlížeč tak dostává malou odpověď.
- Vlastní API přijímá bbox jako `minLon,minLat,maxLon,maxLat` (běžné pro mapy); prohození os pro ČÚZK dělá až `Bbox::toCuzkParam()`.
- Limit oblasti podle plochy (nejvýše 0,000101 °², strana nejvýše 0,03 °): zátěž závisí na počtu parcel, ne na délce strany. Hodnotu jsem určil měřením (viz Výkon).
- Parcely se načítají od přiblížení 18, jen pro viditelnou oblast, s prodlevou 250 ms po zastavení mapy a se zrušením zastaralých požadavků (`AbortController`), aby mapa zůstala plynulá.
- Čisté PHP bez frameworku: endpointy jsou jen dva a s PHP i s frameworky mám zatím málo zkušeností, takže jsem chtěl rozumět každé části kódu.
- Cache odpovědí ČÚZK v souborech (`cache/`, platnost 1 den): ČÚZK je nejpomalejší část. Oblast před dotazem zaokrouhluji na mřížku 0,002 °, aby i mírně posunutý pohled mapy použil stejný soubor. Když ČÚZK neodpoví (časový limit 10 s), vrátím starší data z cache místo chyby; `502` přijde jen tehdy, když v cache nic není.
- Frontend v Reactu (Vite, TypeScript, Leaflet). Sestavená aplikace je v
  `public/app/`, takže ke spuštění stačí PHP. Starší verze mapy bez sestavení
  (`public/map.html`) zůstala jako záloha.

## Co mě překvapilo

- ČÚZK v bboxu čeká nejdřív šířku a pak délku (lat,lon), kdežto GeoJSON a
  Leaflet používají lon,lat. Když jsem je poslal v opačném pořadí, služba
  nevrátila chybu, ale nula parcel. Prohození proto dělám na jednom místě,
  v `Bbox::toCuzkParam()`.
- WFS mlčky ignoruje neznámé parametry. Kvůli překlepu v `srsName` mi přišla
  data v S-JTSK místo EPSG:4326. Nepoznal jsem to podle chyby, ale podle
  velikosti odpovědi a prvních souřadnic. Od té doby kontroluji i obsah
  odpovědi, ne jen její velikost.
- GeoJSON z ČÚZK neobsahuje atributy parcel, jen geometrii. Proto čtu GML
  a GeoJSON skládám v PHP sám.
- Parcely mají díry. V XML bylo víc seznamů souřadnic než parcel (165 pro
  159 parcel), protože některé parcely mají vnitřní hranice (`gml:interior`).
  Polygon proto skládám z vnějšího a z vnitřních kruhů.
- Čekal jsem, že bude zdržovat zpracování XML, ale zdržuje ČÚZK. Při měření
  trvalo stažení 0,36 až 2,14 s a zpracování v PHP jen 0,04 až 0,08 s. Proto
  jsem přidal cache, ne optimalizaci parseru.
- Limit na délku strany oblasti nebyl dobrý nápad. Na monitoru Full HD byla
  viditelná oblast při přiblížení 18 asi o 0,0002 ° širší než můj limit a API
  vrátilo 400, i když byla podle plochy asi třikrát menší než povolený čtverec.
  Limit jsem proto předělal na plochu.
- Vestavěný server PHP vrací při fatal erroru status 200 a text chyby v HTML.
  Při testování proto kontroluji i `Content-Type`, ne jen status.

## Výkon

Měřil jsem odezvu endpointu `/api/parcels` pro různě velké oblasti v centru Jičína (3 opakování, `curl -w "%{time_total}"`):

| Oblast                              | Čas            | Velikost odpovědi |
| ----------------------------------- | -------------- | ----------------- |
| 0,005 ° × 0,005 °                   | 0,23 až 0,33 s | 155 KB            |
| 0,010 ° × 0,010 ° (asi 1800 parcel) | 1,26 až 1,72 s | 772 KB            |
| 0,015 ° × 0,015 °                   | 1,64 až 2,01 s | 1,99 MB           |

Tabulka výše je z doby před zavedením cache a zaokrouhlení oblasti.

Čas jsem rozdělil na stažení z ČÚZK a zpracování v PHP (jedno měření):
stažení trvalo 0,36 až 2,14 s, zpracování v PHP jen 0,04 až 0,08 s. Hlavní
brzdou je tedy služba ČÚZK. Proto jsem omezil velikost oblasti a parcely se při
menším přiblížení nenačítají.

GeoJSON, který posílám prohlížeči, je asi 3,8krát menší než GML z ČÚZK
(97 KB oproti 369 KB pro stejnou oblast s 159 parcelami).

Velká oblast (kolem 0,07 ° × 0,07 °) shodila PHP kvůli limitu paměti 128 MB, protože SimpleXML načítá celé XML najednou. Proto má `Bbox` limit na plochu.

Čísla jsou orientační: jedno měření v centru města, kde jsou parcely nejhustší, a odezva ČÚZK kolísá.

### Cache

Odpovědi ČÚZK ukládám do složky `cache/`. Jedno měření:

| Dotaz                                                  | Bez cache | S cache |
| ------------------------------------------------------ | --------- | ------- |
| střed Jičína (0,003 ° × 0,002 °)                       | 0,24 s    | 0,010 s |
| oblast odpovídající monitoru Full HD při přiblížení 18 | 0,81 s    | 0,029 s |

Cena zaokrouhlení oblasti na mřížku: dotaz je o něco větší než viditelná oblast
(odpověď například 128 KB místo 97 KB).

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

## Omezení a co bych řešil jinak

- Cache se sama nečistí: staré soubory ve složce `cache/` zůstávají, dokud je někdo nesmaže.
- Při menším přiblížení než 18 se parcely nezobrazují, jen nápověda. Lepší by bylo ukázat alespoň hranice katastrálních území.
- SimpleXML načítá celé XML do paměti; pro velké oblasti bych použil proudové čtení (`XMLReader`).
- Frontend nekontroluje tvar odpovědi API za běhu (jen TypeScript typy).
- Chybí automatické testy (například pro `Bbox`, `GmlParser` a cache).

## Vývoj frontendu

Zdrojový kód je ve složce `web/` (React, Vite, TypeScript).

    cd web
    npm install
    npm run dev       # http://localhost:5173/app/, /api se přeposílá na PHP (port 8000)
    npm run build     # sestaví aplikaci do ../public/app
