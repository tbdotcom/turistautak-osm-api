<?php 

/**
 * térképi objektumok letöltése osm fájlként
 *
 * 2015.02.01 előtt csak hitelesítéssel és jogosultsággal működött
 * az ODbL nyitással ezt kikapcsoltam
 *
 * közvetlenül a MySQL adatbázist olvassa
 * felhasznál php összetevőket is a turistautak.hu-ból
 * például a beállításokat, típusdefiníciós tömböket
 *
 * @todo ötletek
 * geometriai index használata (sajnos a táblák nem MyISAM-ok)
 * objektum-orientált megvalósítás, szétválasztás részekre
 *
 * @author Kolesár András <kolesar@turistautak.hu>
 * @since 2014.06.09
 *
 * Tagek kigyujtese include fajlba - Zelena Endre <gps@turablog.com>
 * 2015.02.04.
 *
 */

require('../include_general.php');
require('../include_arrays.php');
require('../poi-type-array.inc.php');
include('include/postgresql.conf.php');
require('include_tagsdef.php');

ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
mb_internal_encoding('UTF-8');

$távolság = 15; // házszámok a vonaltól
$végétől = 25; // az utca végétől

try {

if (date('Y-m-d') < '2015-02-01' && !allow_download($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
	$realm = 'turistautak.hu';
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
    header('HTTP/1.0 401 Unauthorized');
	exit;
}

// ezzel adtam megjegyzéseket a típuskódokhoz a forráskódban
if (isset($_REQUEST['comment-types']) && $_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR']) comment_types();

// bounding box
if (@$_REQUEST['bbox'] == '') throw new Exception('no bbox');
$bbox = explode(',', $_REQUEST['bbox']);
if (count($bbox) != 4) throw new Exception('invalid bbox syntax');
if ($bbox[0]>=$bbox[2] || $bbox[1]>=$bbox[3]) throw new Exception('invalid bbox');
foreach ($bbox as $coord) if (!is_numeric($coord)) throw new Exception('invalid bbox');

$area = ($bbox[2]-$bbox[0])*($bbox[3]-$bbox[1]);
if ($area>0.25) throw new Exception('bbox too large');

$nd = array();
$ways = array();
$rels = array();
$nodetags = array();

echo "<?xml version='1.0' encoding='UTF-8'?>", "\n";
echo "<osm version='0.6' upload='false' generator='turistautak.hu'>", "\n";
echo sprintf("  <bounds minlat='%1.7f' minlon='%1.7f' maxlat='%1.7f' maxlon='%1.7f' origin='turistautak.hu' />", $bbox[1], $bbox[0], $bbox[3], $bbox[2]), "\n";

// letöltjük az osm adatokat is
if ($mod == 'osm') {
	$url = 'http://api.openstreetmap.org/api/0.6/map?bbox=' . implode(',', $bbox);
	echo '<!-- ' . $url . ' -->';
	$osm = file($url);
	$linecount = count($osm);
	for ($i=2; $i<$linecount-1; $i++) echo $osm[$i];
	echo '<!-- ' . $url . ' -->';
}

// poi
$sql = sprintf("SELECT
	poi.*,
	poi_types.wp,
	poi_types.name AS typename,
	owner.member AS ownername,
	useruploaded.member AS useruploadedname
	FROM geocaching.poi
	LEFT JOIN geocaching.poi_types ON poi.code = poi_types.code
	LEFT JOIN geocaching.users AS owner ON poi.owner = owner.id
	LEFT JOIN geocaching.users AS useruploaded ON poi.useruploaded = useruploaded.id
	WHERE poi.deleted=0
	AND poi.lon>=%1.7f
	AND poi.lat>=%1.7f
	AND poi.lon<=%1.7f
	AND poi.lat<=%1.7f
	AND poi.code NOT IN (0xad02, 0xad03, 0xad04, 0xad05, 0xad06, 0xad07, 0xad08, 0xad09, 0xad0a, 0xad00)
	",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

if (is_array($rows)) foreach ($rows as $myrow) {

	$node = sprintf('%1.7f,%1.7f', $myrow['lat'], $myrow['lon']);
	
	if (isset($nd[$node])) {
		$ref = $nd[$node];
	} else {
		$ref = refFromNode($node);
		$nd[$node] = $ref;
	}
	$ndrefs[] = $ref;
	
	$tags = array(
		'Type' => sprintf('0x%02x %s', $myrow['code'], tr($myrow['typename'])),
		'Label' => tr($myrow['nickname']),
		'ID' => $myrow['id'],
		'Magassag' => $myrow['altitude'],
		'Letrehozta' => sprintf('%d %s', $myrow['owner'], tr($myrow['ownername'])),
		'Letrehozva' => $myrow['dateinserted'],
		'Modositotta' => sprintf('%d %s', $myrow['useruploaded'], tr($myrow['useruploadedname'])),
		'Modositva' => $myrow['dateuploaded'],
		'ID' => $myrow['id'],
		'Leiras' => tr($myrow['fulldesc']),
		'Megjegyzes' => tr($myrow['notes']),
	);

	$attributes = array();
	foreach (explode("\n", $myrow['attributes']) as $attribute) {
		if (preg_match('/^([^=]+)=(.+)$/', $attribute, $regs)) {
			$key = tr($regs[1]);
			$value = tr($regs[2]);
			
			if (isset($poi_attributes_def[$regs[1]])) {
				$def = $poi_attributes_def[$regs[1]];
				if ($def['datatype'] == 'attributes' && $value[0] == 'A') {
					$values = explode(';', tr(trim($def['options'])));
					$attributes[$key] = array();
					for ($i=0; $i<strlen($value); $i++) {
						if ($value[$i+1] == '+') {
							$attributes[$key][] = trim($values[$i]);
						}
					}
					$value = implode('; ', $attributes[$key]);
				}
			}
			
			$tags['POI:' . $key] = $value;
		}
	}

	$tags['[----------]'] = '[----------]';
	$tags['name'] = preg_replace('/ \.\.$/', '', $tags['Label']);
	$name = null;

	$tags = array_merge($tags, poiCodeToOSM(@$myrow['code']));	

	switch (@$myrow['code']) {

			case 0xa205: // közkút
			case 0xa206: // elzárt közkút
			case 0xa207: // tűzcsap
			case 0xa208: // szökőkút
				$name = false;
				break;
	
			case 0xa303: // templom
				if (preg_match('/\\b(g\\.? ?k|görög kat.*)\\b/iu', $tags['Label'])) {
					$tags['religion'] = 'christian';
					$tags['denomination'] = 'greek_catholic';
				} else if (preg_match('/\\b(r\\.? ?k|kat\.|római kat.*)\\b/iu', $tags['Label'])) {
					$tags['religion'] = 'christian';
					$tags['denomination'] = 'roman_catholic';
				} else if (preg_match('/\\b(református|ref\.)\\b/iu', $tags['Label'])) {
					$tags['religion'] = 'christian';
					$tags['denomination'] = 'reformed';
				} else if (preg_match('/\\b(evangélikus|ev\.)\\b/iu', $tags['Label'])) {
					$tags['religion'] = 'christian';
					$tags['denomination'] = 'lutheran';
				}
				break;
	
			case 0xa502: // kereszt
				if (in_array(@$tags['Label'], array('Kereszt', 'Feszület'))) $name = false;
				break;
	
			case 0xa610: // vasúti átjáró
			case 0xa612: // taxiállomás
				$name = false;
				break;
	
			case 0xa706: // orvosi rendelő
				if (@$tags['Label'] == 'Orvosi rendelő') $name = false; 
				break;
	
			case 0xa707: // gyógyszertár
				if (@$tags['Label'] == 'Gyógyszertár') $name = false; 
				break;
	
			case 0xa70a: // nyilvános telefon
				if (preg_match('/^[0-9]+-/', $tags['name'])) {
					$tags['payment:telephone_cards'] = 'yes';
				} else if (preg_match('/^[0-9]+\\+/', $tags['name'])) {
					$tags['payment:coins'] = 'yes';
				}
				$tags['phone'] = preg_replace('/^([0-9]+)(\\+|-)/', '\\1 ', $tags['name']);
				if (!preg_match('/^\\+36 ?/', $tags['phone'])) {
					$tags['phone'] = '+36 ' . $tags['phone'];
				}
				$name = false;
				break;
	
			case 0xa70b: // parkoló
				if (@$tags['Label'] == 'Parkoló') $name = false; 
				break;
	
			case 0xa70c: // posta
			case 0xa70d: // postaláda
				$name = false; 
				break;
	
			case 0xa70f: // rendőrség
				if (@$tags['Label'] == 'Rendőrség') $name = false; 
				break;
	
			case 0xaa00: // épített tereptárgy
				// erdőhatár-jelek, leginkább fából
				if (preg_match('#^[0-9/]+$#', $tags['name'])) {
					$tags['ref'] = $tags['name'];
					$tags['boundary'] = 'marker';
					$tags['marker'] = 'wood';
					$name = false;
				} else if ($tags['name'] == 'Harangláb') {
					$tags['man_made'] = 'campanile';
					$name = false;
				}
				break;
	
			case 0xaa03: // gyár
			case 0xaa06: // rádiótorony
			case 0xaa07: // kémény
			case 0xaa08: // víztorony
			case 0xaa0a: // esőház
			case 0xaa0c: // információs tábla
			case 0xaa0e: // kapu
				$name = false; 
				break; 
	
			case 0xaa10: // magasles
				if (preg_match('/fedett/i', @$tags['Label'])) $tags['shelter'] = 'yes';
				$name = false; 
				break;
	
			case 0xaa11: // pihenőhely
				if (in_array(@$tags['Label'], array('Pihenőhely', 'Pihenő'))) $name = false;
				break;
	
			case 0xaa12: // pad
				if (@$tags['Label'] == 'Pad') $name = false;
				break;
	
			case 0xaa13: // tűzrakóhely
				if (@$tags['Label'] == 'Tűzrakóhely') $name = false;
				break;
	
			case 0xaa14: // sorompó
			case 0xaa16: // háromszögelési pont
				$name = false; 
				break;
	
			case 0xaa17: // határkő
				if (@$tags['Label'] == 'Határkő') $name = false;
				break;
	
			case 0xaa2a: // km-/útjelzőkő
				if (preg_match('/([0-9]+)/iu', $tags['Label'], $regs)) {
					$tags['distance'] = $regs[1];
				}
				$name = false; 
				break;
	
			case 0xaa34: // vízmű
				$name = false; 
				break;
	
			case 0xaa36: // transzformátor
				if (preg_match('/([0-9]+)/', $tags['Label'], $regs))
					$tags['ref'] = $regs[1];
				if (preg_match('/otr/iu', $tags['Label'], $regs)) {
					$tags['power'] = 'pole';
					$tags['transformer'] = 'distribution';
				}
				$name = false;
				break;
	
			case 0xaa37: // játszótér
				if (@$tags['Label'] == 'Játszótér') $name = false;
				break;
	
			case 0xab02: // fa
				if (@$tags['Label'] == 'Fa') $name = false;
				break;
	
			case 0xab03: // gázló
				if (@$tags['Label'] == 'Gázló') $name = false;
				break;
	
			case 0xab04: // dagonya
				if (@$tags['Label'] == 'Dagonya') $name = false;
				break;
	
			case 0xab0a: // magaslat
				$tags['ele'] = $tags['magassag']; // ezt más ponttípusok is megkaphatnák, melyek?
				break;
	
			case 0xab0b: // kilátás
				$name = false;
				break;
	
			case 0xab0c: // szikla
				if (@$tags['Label'] == 'Szikla') $name = false;
				break;
	
			case 0xab0d: // vízesés
				if (@$tags['Label'] == 'Vízesés') $name = false;
				break;
	
			case 0xac02: // szelektív hulladékgyűjtő
			case 0xac03: // hulladéklerakó
			case 0xac04: // hulladékgyűjtő
			case 0xac05: // konténer
				$name = false;
				break;
				
			case 0xae06: // turistaút csomópont, szakértője: modras
				$tags['ele'] = $tags['Magassag'];
				break;
		}
	
	if ($name === false) unset($tags['name']);
	$tags['url'] = 'http://turistautak.hu/poi.php?id=' . $myrow['id'];
	
	$tags['email'] = @$tags['POI:email'];
	
	if (@$tags['POI:telefon'] != '' && $tags['POI:mobil'] != '' && $tags['POI:telefon'] != $tags['POI:mobil']) {
		$tags['phone'] = $tags['POI:telefon'] . '; ' . $tags['POI:mobil'];
	} else if ($tags['POI:telefon'] != '') {
		$tags['phone'] = $tags['POI:telefon'];	
	} else if ($tags['POI:mobil'] != '') {
		$tags['phone'] = $tags['POI:mobil'];
	}

	$tags['fax'] = @$tags['POI:fax'];
	$tags['website'] = @$tags['POI:web'];
	$tags['addr:postcode'] = @$tags['POI:irányítószám'];
	$tags['addr:street'] = @$tags['POI:cím'];
	$tags['opening_hours'] = @$tags['POI:nyitvatartás'];
	$tags['operator'] = @$tags['POI:hálózat'];
	$tags['gsm:LAC'] = @$tags['POI:lac'];
	$tags['gsm:cellid'] = @$tags['POI:cid'];
	$tags['gsm:cellid'] = @$tags['POI:cid'];
	$tags['internet_access:ssid'] = @$tags['POI:essid'];
	$tags['cave:ref'] = @$tags['POI:kataszteri szám'];
	
	// kivesszük a nyitva tartást a leírásból
	if (!isset($tags['opening_hours']) && preg_match('/^Nyitva ?tartás ?:?(.+)$/imu', $tags['Leiras'], $regs)) {
		$tags['opening_hours'] = trim($regs[1]);
	}
	
	// átalakítjuk a nyitva tartást osm szintaktikára
	$tags['opening_hours'] = preg_replace('/\b(H|Hét|Hétfő)\b/i', 'Mo', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(K|Ked|Kedd)\b/i', 'Tu', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(S|Sze|Szerda)\b/i', 'We', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(Cs|Csü|Csürtörtök)\b/i', 'Th', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(P|Pén|Péntek)\b/i', 'Fr', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(Sz|Szo|Szombat)\b/i', 'Sa', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace('/\b(V|Vas|Vasárnap)\b/i', 'Su', $tags['opening_hours']);

	$tags['opening_hours'] = preg_replace("/(?<![0-9:])([0-9]+)(?![0-9:])/i", '\\1:00', $tags['opening_hours']);
	$tags['opening_hours'] = preg_replace("/(?<![0-9:])([0-9]:)/i", '0\\1', $tags['opening_hours']);
	
/*
étterem tulajdonságai: vegetáriánus konyha; nemdohányzó helyiség; légkondicionálás; fizetés kártyával
pihenőhely tulajdonságai: nyilvános WC; ivóvíz; szemeteskuka; kávé, tea; szendvics; meleg étel
barlang tulajdonságai: bivakhelynek megfelel; nyitott (nincs lezárva); kötél szükséges hozzá
hulladékfajták: papír; színes üveg; fehér üveg; fémpalack; PET palack; akkumulátor; fémhulladék; egyéb veszélyes hulladék
váróhelység nincs; megálló beállóval; állomás váróteremmel; pályaudvar
*/					
	
	foreach ($attributes as $key => $attribute) {
		foreach ($attribute as $value) {
			switch ($value) {
				case 'vegetáriánus konyha':
					$tags['diet:vegetarian'] = 'yes';
					break;
					
				case 'nemdohányzó helyiség':
					// ma már sehol sem lehet dohányozni
					break;

				case 'fizetés kártyával':
					$tags['payment:debit_cards'] = 'yes';
					$tags['payment:credit_cards'] = 'yes';
					break;

				case 'nyilvános WC':
					$tags['amenity'] = 'toilets';
					break;
					
				case 'ivóvíz':
					$tags['amenity'] = 'drinking_water';
					break;
					
				case 'szemeteskuka':
					$tags['amenity'] = 'waste_basket';
					break;
					
				case 'papír':
					$tags['recycling:paper'] = 'yes';
					break;
					
				case 'színes üveg':
				case 'fehér üveg':
					$tags['recycling:glass'] = 'yes';
					break;

				case 'fémpalack':
					$tags['recycling:cans'] = 'yes';
					break;
					
				case 'PET palack':
					$tags['recycling:plastic_bottles'] = 'yes';
					break;
					
				case 'akkumulátor':
					$tags['recycling:batteries'] = 'yes';
					break;

				case 'fémhulladék':
					$tags['recycling:scrap_metal'] = 'yes';
					break;

			}
		}
	}
	
	/* váróhelység: nincs; megálló beállóval; állomás váróteremmel; pályaudvar */
	switch ($tags['POI:váróhelység']) {
		case 'nincs':
			$tags['shelter'] = 'no';
			break;

		case 'megálló beállóval':
			$tags['shelter'] = 'yes';
			break;

		case 'állomás váróteremmel':
			$tags['shelter'] = 'yes';
			$tags['building'] = 'yes';
			break;

		case 'pályaudvar':
			$tags['amenity'] = 'bus_station';
			break;

	}
	
	/* étterem típusa: étterem; pizzéria; cukrászda; büfé; söröző; kocsma; teaház; presszó */
	if ($myrow['code'] == 0xa103 || $myrow['code'] == 0xa100) switch ($tags['POI:étterem típusa']) {
		case 'pizzéria':
			$tags['cuisine'] = 'pizza';
			break;

		case 'cukrászda':
			$tags['shop'] = 'confectionery';
			break;

		case 'büfé':
			$tags['amenity'] = 'fast_food';
			break;

		case 'söröző':
			$tags['amenity'] = 'pub';
			break;

		case 'kocsma':
			$tags['amenity'] = 'pub';
			break;

		case 'teaház':
			$tags['shop'] = 'tea'; // ??
			break;

		case 'presszó':
			$tags['amenity'] = 'cafe';
			break;

	}	
	
	/* igazolás típusa: bélyegző; kód; matrica; egyéb */
	/* 16395 Dezsővár 47.924417, 19.909033 */
	if ($myrow['code'] == 0xad01) switch ($tags['POI:igazolás típusa']) {
		case 'bélyegző':
			$tags['checkpoint:type'] = 'stamp';
			break;

		case 'kód':
			$tags['checkpoint:type'] = 'code';
			break;

		case 'matrica':
			$tags['checkpoint:type'] = 'sticker';
			break;
	}	

	/* szállás típusa: szálloda; panzió; vendégház; turistaház; kulcsosház; kemping */
	/* 1705 Slano 42.582633 18.209050 kemping */
	if ($myrow['code'] == 0xa400) switch ($tags['POI:szállás típusa']) {

		case 'szálloda':
			$tags['tourism'] = 'hotel';
			break;

		case 'panzió':
		case 'vendégház':
		case 'turistaház':
			$tags['tourism'] = 'guest_house';
			break;

		case 'kulcsosház':
			$tags['tourism'] = 'chalet';
			break;

		case 'kemping':
			$tags['tourism'] = 'camp_site';
			break;
	}
	
	if ($tags['POI:díjszabás'] == 'ingyenes') $tags['fee'] = 'no';
	if (preg_match('/ingyen/i', $tags['Label'])) $tags['fee'] = 'no'; // poi 2838
	
	// forrás
	$tags['source'] = 'turistautak.hu';

	$nodetags[$ref] = $tags;
}

// lines
$sql = sprintf("SELECT
	segments.*,
	userinserted.member AS userinsertedname,
	usermodified.member AS usermodifiedname
	FROM segments
	LEFT JOIN geocaching.users AS userinserted ON segments.userinserted = userinserted.id
	LEFT JOIN geocaching.users AS usermodified ON segments.usermodified = usermodified.id
	WHERE deleted=0
	AND lon_max>=%1.7f
	AND lat_max>=%1.7f
	AND lon_min<=%1.7f
	AND lat_min<=%1.7f",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$wkt = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.7f,%1.7f', $coords[0], $coords[1]);
			if (isset($nd[$node])) {
				$ref = $nd[$node];

				// ha olyan node-ba futottunk, ami már volt,
				// akkor levesszük róla a fixme=continue-t
				// mivel megtaláltuk a felmérendő út belső végét
				unset($nodetags[$ref]['fixme']);
				unset($nodetags[$ref]['noexit']);

			} else {
				$ref = refFromNode($node);
				$nd[$node] = $ref;

				// ezt csak akkor vizsgáljuk le, ha még nem volt ez a node,
				// hiszen ha volt, akkor már nem külső vég
				if (($node_id == 0) && ($myrow['code'] == 0xd1)) $nodetags[$ref]['fixme'] = 'continue';
				if (($node_id == $nodecount-1) && ($myrow['code'] == 0xd1)) $nodetags[$ref]['fixme'] = 'continue';

				// ezt is, mert csak külső végre van értelme
				if (($node_id == 0) && ($myrow['blind'] & 1)) $nodetags[$ref]['noexit'] = 'yes';
				if (($node_id == $nodecount-1) && ($myrow['blind'] & 2)) $nodetags[$ref]['noexit'] = 'yes';

			}
			$ndrefs[] = $ref;
			$wkt[] = sprintf('%1.7f %1.7f', $coords[1], $coords[0]);
			
		}
	}
	
	$attr = array(
		'id' => -$myrow['id'],
		// 'version' => '999999999',
	);
	
	$tags = array();

	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], line_type($myrow['code']));

	foreach ($GLOBALS['segment_attributes'] as $id => $array) {

		if (null !== $array[1]) {
			$field = $array[1];
		} else {
			$field = $id;
		}

		if (!is_null(@$myrow[$field])) {
			$tags[$id] = tr($myrow[$field]);
		}
		
	}

	// felülírunk címkéket	
	$tags['Letrehozta'] = sprintf('%d %s', $myrow['userinserted'], tr($myrow['userinsertedname']));
	if (isset($tags['Modositotta']))
		$tags['Modositotta'] = sprintf('%d %s', $myrow['usermodified'], tr($myrow['usermodifiedname']));
		
	// törlünk címkéket
	unset($tags['Del']);
	unset($tags['Csatlakozik']);
	unset($tags['EmelkedesOda']);
	unset($tags['EmelkedesVissza']);
	unset($tags['Hossz']);
	unset($tags['HosszFerde']);
	unset($tags['From']);
	unset($tags['To']);

	// ezt csak akkor, ha nincs
	if (!$tags['Ivelve']) unset($tags['Ivelve']);
	if (!$tags['MindenElag']) unset($tags['MindenElag']);
	if (!$tags['DirIndicator']) unset($tags['DirIndicator']);
	if (!$tags['Zsakutca']) unset($tags['Zsakutca']);
	
	// forrás
	$tags['source'] = 'turistautak.hu';

	$tags['[----------]'] = '[----------]';

	switch ($myrow['code']) {
		case 0x0081: // csapás
		case 0x0082: // ösvény
		case 0x0083: // gyalogút
			$tags['highway'] = 'path';
			break;

		case 0x0084: // szekérút
		case 0x0085: // földút
			$tags['highway'] = 'track';
			break;

		case 0x0086: // burkolatlan utca
			$tags['highway'] = 'residential';
			$tags['surface'] = 'unpaved';
			break;

		case 0x0087: // makadámút
			$tags['highway'] = 'track';
			$tags['tracktype'] = 'grade1';
			break;

		case 0x0091: // burkolt gyalogút
			$tags['highway'] = 'footway';
			break;

		case 0x0092: // kerékpárút
			$tags['highway'] = 'cycleway';
			break;

		case 0x0093: // utca
		case 0x0094: // kiemelt utca
			$tags['highway'] = 'residential';
			break;

		case 0x0095: // országút
			$tags['highway'] = 'tertiary';
			break;

		case 0x0096: // másodrendű főút
			$tags['highway'] = 'secondary';
			break;

		case 0x0097: // elsőrendű főút
			$tags['highway'] = 'primary';
			break;

		case 0x0098: // autóút
			$tags['highway'] = 'trunk';
			break;

		case 0x0099: // autópálya
			$tags['highway'] = 'motorway';
			break;

		case 0x009a: // erdei aszfalt
		case 0x009b: // egyéb közút
			$tags['highway'] = 'unclassified';
			break;

		case 0x00a2: // körforgalom
			$tags['junction'] = 'roundabout';
			break;

		case 0x00a3: // lépcső
			$tags['highway'] = 'steps';
			break;

		case 0x00a4: // kifutópálya
			$tags['aeroway'] = 'runway';
			break;

		case 0x00b1: // folyó
			$tags['waterway'] = 'river';
			break;

		case 0x00b2: // patak
			$tags['waterway'] = 'stream';
			break;

		case 0x00b3: // időszakos patak
			$tags['waterway'] = 'stream';
			$tags['intermittent'] = 'yes';
			break;

		case 0x00b4: // komp
			$tags['route'] = 'ferry';
			break;

		case 0x00b5: // csatorna
			$tags['waterway'] = 'ditch';
			break;

		case 0x00c1: // vasút
			$tags['railway'] = 'rail';
			break;

		case 0x00c2: // kisvasút
			$tags['railway'] = 'narrow_gauge';
			break;

		case 0x00c3: // villamos
			$tags['railway'] = 'tram';
			break;

		case 0x00c4: // kerítés
			$tags['barrier'] = 'fence';
			break;

		case 0x00c5: // elektromos vezeték
			$tags['power'] = 'line';
			break;

		case 0x00c6: // csővezeték
			$tags['man_made'] = 'pipeline';
			break;

		case 0x00c7: // kötélpálya
		case 0x00c8: // 
		case 0x00c9: // 
			$tags['aerialway'] = 'chair_lift';
			break;

		case 0x00d3: // vízpart
			$tags['natural'] = 'coastline';
			break;

		case 0x00d4: // völgyvonal
			$tags['natural'] = 'valley';
			break;

		case 0x00d5: // megyehatár
			$tags['boundary'] = 'administrative';
			$tags['admin_level'] = '2';
			break;

		case 0x00d6: // országhatár
			$tags['boundary'] = 'administrative';
			$tags['admin_level'] = '6';
			break;

	}

	$tags['traces'] = @$myrow['tracks'];
	$tags['name'] = tr(trim(@$myrow['Utcanev']) != '' ? $myrow['Utcanev'] : @$myrow['Nev']);
	$tags['ref'] = tr(@$myrow['Utnev']);
	if (@$tags['junction'] != 'roundabout' && !isset($tags['waterway'])) $tags['oneway'] = @$myrow['dirindicator'] == '1' ? 'yes' : null;
	$tags['surface'] = burkolat(tr(trim(@$myrow['Burkolat'])));
	$tags['maxspeed'] = tr(trim(@$myrow['KorlatozasSebesseg']));
	
	if (preg_match('/rossz|tönkrement/', tr(trim(@$myrow['Burkolat'])))) {
		$tags['smoothness'] = 'bad';
	}

	// vannak autós-bicicklis járhatósági paraméterek vasúton, ezt nem kérjük
	if (!isset($tags['railway']) && $tags['highway'] != 'steps') {

		if ($tags['highway'] != 'footway' && $tags['highway'] != 'path' && $tags['highway'] != 'cycleway') {
			$smoothness = JarhatosagAutoval(tr(trim(@$myrow['JarhatosagAutoval'])));
			if ($smoothness != '') $tags['smoothness'] = $smoothness;
		}

		if (@$myrow['JarhatosagBiciklivel'] == 'A' &&
			($tags['highway'] == 'cycleway' || @$myrow['JarhatosagAutoval'] == '')) $tags['smoothness'] = 'good';
		if (@$myrow['JarhatosagBiciklivel'] == 'B') $tags['smoothness'] = 'bad';
		if (@$myrow['JarhatosagBiciklivel'] == 'C') $tags['smoothness'] = 'horrible';
		if (@$myrow['JarhatosagBiciklivel'] == 'D') $tags['smoothness'] = 'impassable';

		if ($tags['highway'] != 'footway' && $tags['highway'] != 'path' && $tags['highway'] != 'cycleway') {
			if (@$myrow['BehajtasAutoval'] == 'B') $tags['toll'] = 'yes';
			if (@$myrow['BehajtasAutoval'] == 'C') $tags['motor_vehicle'] = 'private';
			if (@$myrow['BehajtasAutoval'] == 'D') $tags['motor_vehicle'] = 'no';
		}

		if (@$myrow['BehajtasBiciklivel'] == 'B') $tags['toll:bicycle'] = 'yes';
		if (@$myrow['BehajtasBiciklivel'] == 'C') $tags['bicycle'] = 'private';
		if (@$myrow['BehajtasBiciklivel'] == 'D') $tags['bicycle'] = 'no';
	}
	
	$tags['maxweight'] = tr(trim(@$myrow['KorlatozasSuly']));
	$tags['maxweight'] = preg_replace("/([0-9])([a-z]+)$/i", '\1 \2', trim($tags['maxweight']));

	if ($myrow['Ivelve']) $tags['complete:curves'] = 'yes';
	if ($myrow['MindenElag']) $tags['complete:intersections'] = 'yes';
			
	$ways[] = array(
		'attr' => $attr,
		'nd' => $ndrefs,
		'tags' => $tags,
	);
	
	// házszámok
	if (@$tags['Numbers'] != '') {
		// N/A|0,O,1,17,E,2,24,8956,8956,Páka,Zala megye,Magyarország,Páka,Zala megye,Magyarország
		$parts = explode('|', $tags['Numbers']);

		$részek = array();
		foreach ($parts as $part) {
			$arr = explode(',', trim($part));
			$nodeindex = $arr[0];
			if (!is_numeric($nodeindex)) continue;
			if ($id) $részek[$id-1]['endnode'] = $nodeindex;
			$részek[$id]['startnode'] = $nodeindex;
			$részek[$id]['arr'] = $arr;
			$id++;
		}
		$részek[$id-1]['endnode'] = null;
		
		foreach ($részek as $id => $rész) {
		
			$arr = $rész['arr'];
			
			// értelmezzük a sort
			$házszám = array();
			
			$házszám['bal']['számozás'] = trim($arr[1]);
			$házszám['bal']['első'] = trim($arr[2]);
			$házszám['bal']['utolsó'] = trim($arr[3]);
			$házszám['jobb']['számozás'] = trim($arr[4]);
			$házszám['jobb']['első'] = trim($arr[5]);
			$házszám['jobb']['utolsó'] = trim($arr[6]);
			$házszám['bal']['irányítószám'] = trim($arr[7]);
			$házszám['jobb']['irányítószám'] = trim($arr[8]);
			$házszám['bal']['település'] = trim($arr[9]);
			$házszám['bal']['megye'] = trim($arr[10]);
			$házszám['bal']['ország'] = trim($arr[11]);
			$házszám['jobb']['település'] = trim($arr[12]);
			$házszám['jobb']['megye'] = trim($arr[13]);
			$házszám['jobb']['ország'] = trim($arr[14]);

			// felépítjük a geometriát WKT-ben
			$wktstring = sprintf('LINESTRING(%s)', implode(', ',
				array_slice($wkt,
					$rész['startnode'],
					$rész['endnode']
				)));
			
			$részek[$id] = array(
				'wkt' => $wktstring,
				'házszám' => $házszám,
			);
		}
		
		foreach ($részek as $rész) {
		
		$pg = pg_connect(PG_CONNECTION_STRING);
		$interpolation = array(
			'O' => 'odd',
			'E' => 'even',
			'B' => 'all',
		);
				
		foreach ($rész['házszám'] as $oldal => $szám) {
		
			if (!isset($interpolation[$szám['számozás']])) continue;
			if ($szám['első'] == '' && $szám['utolsó'] == '') {
				// nincs házszám
				
			} else if ($szám['első'] == $szám['utolsó'] ||
				$szám['első'] == '' ||
				$szám['utolsó'] == ''
				) {

				// egyetlen node
				$sql = sprintf("SELECT
					ST_AsText(
					ST_Transform(
					ST_Line_Interpolate_Point(
					ST_OffsetCurve(
					ST_Transform(
					ST_GeomFromText('%s',
					4326), -- GeomFromText
					3857), -- Transform
					%f), -- ST_OffsetCurve
					0.5), -- Line_Interpolate_Point
					4326) -- Transform
					) -- AsText
					AS geom
					",
						$rész['wkt'],
						($oldal == 'bal' ? 1 : -1) * $távolság
				);
				
				$result = pg_query($sql);
				$row = pg_fetch_assoc($result);
				$newgeom = $row['geom'];

				if (preg_match('/^POINT\(([^ ]+) ([^ ]+)\)$/', $newgeom, $regs)) {
				
					$node = sprintf('%1.7f,%1.7f', $regs[2], $regs[1]);
					$ref = refFromNode($node);
					$nd[$node] = $ref;
					$ndrefs[] = $ref;

					$addrtags = array(
						'addr:city' => $szám['település'],
						'addr:housenumber' => $szám['első'],
						'addr:postcode' => $szám['irányítószám'],
						'addr:street' => $tags['name'],
					);
					$nodetags[$ref] = $addrtags;
				}				

			} else {
				// interpoláció
				$sql = sprintf("SELECT
					ST_AsText(
					ST_Transform(
					ST_Line_Substring(
					ST_OffsetCurve(
					ST_Transform(
					ST_GeomFromText('%1\$s',
					4326), -- GeomFromText
					3857), -- Transform
					%2\$f), -- OffsetCurve
						%3\$f/ST_Length(
							ST_OffsetCurve(
							ST_Transform(
							ST_GeomFromText('%1\$s',
							4326), -- GeomFromText
							3857), -- Transform
							%2\$f) -- OffsetCurve
						), 
						1.0-%3\$f/ST_Length(
							ST_OffsetCurve(
							ST_Transform(
							ST_GeomFromText('%1\$s',
							4326), -- GeomFromText
							3857), -- Transform
							%2\$f) -- OffsetCurve
						)
					), -- Line_Substring
					4326) -- Transform
					) -- AsText
					AS geom
					",
						$rész['wkt'],
						($oldal == 'bal' ? 1 : -1) * $távolság,
						$végétől
				);
				
				$result = pg_query($sql);
				$row = pg_fetch_assoc($result);
				$newgeom = $row['geom'];
				
				if (preg_match('/^LINESTRING\((.+)\)$/', $newgeom, $regs)) {
					$nodes = explode(',', $regs[1]);
					if ($oldal != 'bal') $nodes = array_reverse($nodes);
					$ndrefs = array();
					$firstnode = $lastnode = null;
					foreach ($nodes as $node) {
						$coords = explode(' ', $node);
						$node = sprintf('%1.7f,%1.7f', $coords[1], $coords[0]);
						$ref = refFromNode($node);
						$nd[$node] = $ref;
						$ndrefs[] = $ref;				
						if ($firstnode === null) $firstnode = $ref;
						$lastnode = $ref;

						$ndrefs[] = $ref;
					}
					
					if ($firstnode !== null) {
						$addrtags = array(
							'addr:city' => $szám['település'],
							'addr:housenumber' => $szám['első'],
							'addr:postcode' => $szám['irányítószám'],
							'addr:street' => $tags['name'],
						);
						$nodetags[$firstnode] = $addrtags;
					}
					
					if ($lastnode !== null) {
						$addrtags['addr:housenumber'] = $szám['utolsó'];
						$nodetags[$lastnode] = $addrtags;
					}

					if (count($ndrefs)) {
						$attr = array(
							'id' => sprintf('-3%09d%02d',
								$myrow['id'], ($oldal == 'bal' ? 1 : 2)),
							// 'version' => '999999999',
						);
						$inttags = array(
							'addr:interpolation' => @$interpolation[$szám['számozás']],
						);
					
						$ways[] = array(
							'attr' => $attr,
							'nd' => $ndrefs,
							'tags' => $inttags,
						);
					}	
				}
			}
		}
		} // parts	
	}
	
	if (trim($tags['Label']) != '') {
		foreach (explode(' ', trim($tags['Label'])) as $counter => $jel) {

			$jel = trim($jel);
			
			if (preg_match('/^([KPSZVFE])(.*)$/iu', $jel, $regs)) {
				$szin = $regs[1];
				$forma = $regs[2];
			} else {
				$szin = '';
				$forma = $jel;
			}
			
			$szinek = array(
				'k' => 'blue',
				'p' => 'red',
				's' => 'yellow',
				'z' => 'green',
				'v' => 'purple',
				'f' => 'black',
				'e' => 'gray',
			);

			$formak = array(
				'' => array('bar', ''),
				'+' => array('cross', '+'),
				'3' => array('triangle', '▲'),
				'4' => array('rectangle', '■'),
				'q' => array('dot', '●'),
				'b' => array('arch', 'Ω'),
				'l' => array('L', '▙'),
				'c' => array('circle', '↺'),
				't' => array('T', ':T:'), // ???
			);

			$color = @$szinek[mb_strtolower($szin)];
			$symbol = @$formak[mb_strtolower($forma)];
			
			$name = isset($symbol[1]) ? ($szin . $symbol[1]) : mb_strtoupper($jel);
			$tags = array(
				'jel' => mb_strtolower($jel),
				'name' => $name,
				'network' => $forma == '' ? 'nwn' : 'lwn',
				'route' => 'hiking',
				'type' => 'route',
				'source' => 'turistautak.hu',
			);

			if ($symbol !== null) {
				$face = $symbol[0];
				$tags['osmc:symbol'] = sprintf(
					'%s:white:%s_%s',
					$color, $color, $face
				);
			}
			
			$members = array(
				array(
					'type' => 'way',
					'ref' => $myrow['id'],
				)
			);
			
			$attr = array(
				'id' => sprintf('-2%09d%02d', $myrow['id'], $counter),
				// 'version' => '999999999',
			);
			
			$rel = array(
				'attr' => $attr,
				'members' => $members,
				'tags' => $tags,
				'endnodes' => array($ndrefs[0], $ndrefs[count($ndrefs)-1]),
			);
		
			$rels[] = $rel;

		}
	}
		
}

// polygons
$sql = sprintf("SELECT 
	polygons.*,
	userinserted.member AS userinsertedname,
	usermodified.member AS usermodifiedname
	FROM polygons
	LEFT JOIN geocaching.users AS userinserted ON polygons.userinserted = userinserted.id
	LEFT JOIN geocaching.users AS usermodified ON polygons.usermodified = usermodified.id
	WHERE deleted=0
	AND lon_max>=%1.7f
	AND lat_max>=%1.7f
	AND lon_min<=%1.7f
	AND lat_min<=%1.7f",
	$bbox[0], $bbox[1], $bbox[2], $bbox[3]);

$rows = array_query($sql);

foreach ($rows as $myrow) {

	$nodes = explode("\n", trim($myrow['points']));
	$ndrefs = array();
	$members = array();
	$nodecount = count($nodes);
	foreach ($nodes as $node_id => $node) {
		if (count($coords = explode(';', $node)) >=2) {
			$node = sprintf('%1.7f,%1.7f', $coords[0], $coords[1]);
			$break = (int) @$coords[2];
			if ($break && count($ndrefs)) {
				// bezárjuk a vonalat
				if ($ndrefs[count($ndrefs)-1] != $ndrefs[0])
					$ndrefs[] = $ndrefs[0];
					
				$id = sprintf('%d%s',
							1000000 + $myrow['id'],
							count($members));
							
				$attr = array(
					'id' => -$id,
					// 'version' => '999999999',
				);

				$ways[] = array(
					'attr' => $attr,
					'nd' => $ndrefs,
					// 'tags' => $tags,
				);
				$members[] = array(
					'type' => 'way',
					'ref' => $id,
					'role' => count($members) ? 'inner' : 'outer',
				);
				$ndrefs = array();
			}
			if (isset($nd[$node])) {
				$ref = $nd[$node];
			} else {
				$ref = refFromNode($node);
				$nd[$node] = $ref;
			}
			$ndrefs[] = $ref;
			
		}
	}

	if (count($ndrefs)) {
		// bezárjuk a vonalat
		if ($ndrefs[count($ndrefs)-1] != $ndrefs[0])
			$ndrefs[] = $ndrefs[0];
		
		// ha többrészes, akkor ezt a részt is mentjük
		if (count($members)) {
			$id = sprintf('%d%s',
						1000000 + $myrow['id'],
						count($members));
						
			$attr = array(
				'id' => -$id,
				// 'version' => '999999999',
			);
				
			$ways[] = array(
				'attr' => $attr,
				'nd' => $ndrefs,
				// 'tags' => $tags,
			);

			$members[] = array(
				'type' => 'way',
				'ref' => $id,
				'role' => count($members) ? 'inner' : 'outer',
			);
			$ndrefs = array();
		}
	}
	
	$attr = array(
		'id' => -($myrow['id'] + 1000000),
		// 'version' => '999999999',
	);
	
	$tags = array();

	$tags['ID'] = $myrow['id'];
	$tags['Type'] = sprintf('0x%02x %s', $myrow['code'], polygon_type($myrow['code']));
	
	$tags['Label'] = tr($myrow['label']);
	$tags['Letrehozva'] = $myrow['dateinserted'];
	$tags['Modositva'] = $myrow['datemodified'];
	$tags['Letrehozta'] = sprintf('%d %s', $myrow['userinserted'], tr($myrow['userinsertedname']));
	if (isset($tags['Modositotta']))
		$tags['Modositotta'] = sprintf('%d %s', $myrow['usermodified'], tr($myrow['usermodifiedname']));
		
	// forrás
	$tags['source'] = 'turistautak.hu';
	$tags['name'] = $tags['Label'];

	$tags['[----------]'] = '[----------]';

	switch ($myrow['code']) {
		case 0x81: // erdő
			$tags['landuse'] = 'forest';
			break;

		case 0x82: // fenyves
			$tags['landuse'] = 'forest';
			$tags['leaf_type'] = 'needleleaved';
			break;
			
		case 0x85: // bokros
			$tags['natural'] = 'scrub';
			break;
			
		case 0x86: // szőlő
			$tags['landuse'] = 'vineyard';
			break;
			
		case 0x87: // gyümölcsös
			$tags['landuse'] = 'orchard';
			break;
			
		case 0x90: // víz
		case 0x91: // tenger
		case 0x92: // tó
		case 0x93: // folyó
			$tags['natural'] = 'water';
			break;

		case 0xa0: // település
		case 0xa1: // megyeszékhely
		case 0xa2: // nagyváros
		case 0xa3: // kisváros
		case 0xa4: // nagyközség
		case 0xa5: // falu
		case 0xa6: // településrész
			$tags['landuse'] = 'residential';
			break;

		case 0xb2: // parkoló
			$tags['amenity'] = 'parking';
			break;

		case 0xb1: // épület
			$tags['building'] = 'yes';
			break;

		case 0xba: // temető
			$tags['landuse'] = 'cemetery';
			break;

	}

	$tags['name'] = tr(trim(@$myrow['Label']));
	
	if (count($members)) {
		$tags['type'] = 'multipolygon';
		$rels[] = array(
			'attr' => $attr,
			'members' => $members,
			'tags' => $tags,
		);

	} else {

		$ways[] = array(
			'attr' => $attr,
			'nd' => $ndrefs,
			'tags' => $tags,
		);
	}
		
}

// összefűzzük a jelzett turistautakat
$common = array();
foreach ($rels as $id => $rel) {
	if (!isset($rel['endnodes'])) continue; // csak a jelzés-kapcsolatok érdekelnek
	$common[$rel['tags']['jel']][$rel['endnodes'][0]][] = $id;
	$common[$rel['tags']['jel']][$rel['endnodes'][1]][] = $id;
}

$count = 0;
foreach ($common as $jel => $group) {

	// menet közben írjuk a $group tömböt, ezért élőben kell kiolvasnunk
	foreach (array_keys($group) as $node) { 
	
		try {
			$ids = $group[$node];
			if (count($ids) != 2) continue;

			// ellenőrizzük, nem csináltunk-e előzőleg butaságot
			if (isset($rels[$ids[0]]['deleted'])) throw new Exception('nincs 0');
			if (isset($rels[$ids[1]]['deleted'])) throw new Exception('nincs 1');

			$bal = refs($rels[$ids[0]]['members']);
			$jobb = refs($rels[$ids[1]]['members']);
		
			// megnézzük, mely végén illeszkedik az új kapcsolat
			if ($rels[$ids[1]]['endnodes'][0] == $node) {
				$members = $rels[$ids[1]]['members'];
				$endnode = $rels[$ids[1]]['endnodes'][1];
			} else if ($rels[$ids[1]]['endnodes'][1] == $node) {
				$members = array_reverse($rels[$ids[1]]['members']);
				$endnode = $rels[$ids[1]]['endnodes'][0];
			} else {
				throw new Exception('nem az van a másik kapcsolat végén, amit vártunk');
			}
		
			// önmagába záródik, nem szeretnénk szívni miatta
			if ($endnode == $node) continue;
		
			if (!is_array($members)) {
				throw new Exception('a members nem tömb');
			}

			if (!is_array($rels[$ids[0]]['members'])) {
				throw new Exception('a tagok nem tömb');
			}

			// megnézzük, hogy a megmaradó melyik végéhez illeszkedik
			if ($rels[$ids[0]]['endnodes'][0] == $node) {
				$rels[$ids[0]]['members'] = array_merge(array_reverse($members), $rels[$ids[0]]['members']);
				$rels[$ids[0]]['endnodes'][0] = $endnode;
			} else if ($rels[$ids[0]]['endnodes'][1] == $node) {
				$rels[$ids[0]]['members'] = array_merge($rels[$ids[0]]['members'], $members);
				$rels[$ids[0]]['endnodes'][1] = $endnode;
			} else {
				throw new Exception('nem az van az aktuális kapcsolat végén, amit vártunk');
			}

			// kicseréljük a megszűnő kapcsolat hivatkozását a túlsó végen a megmaradóra
			if (count($group[$endnode]) == 2) {
				if ($group[$endnode][0] == $ids[1]) {
					$group[$endnode][0] = $ids[0];
				} else if ($group[$endnode][1] == $ids[1]) {
					$group[$endnode][1] = $ids[0];
				} else {
					throw new Exception('nem az van a csomópontban, amit vártunk');
				}	
			}
	
			// megjelöljük töröltként
			$rels[$ids[1]]['deleted'] = true;
		
			if (false) {		
				$nodetags[$node][sprintf('illesztés:%d.', $count++)] = sprintf('%s [%s = %s + %s] %s',
					$jel,
					implode(', ', refs($rels[$ids[0]]['members'])),
					implode(', ', $bal),
					implode(', ', $jobb), 
					$endnode);
			}

		} catch (Exception $e) {
			// csendben továbblépünk		
		}	
	}

}

foreach ($nd as $node => $ref) {
	list($lat, $lon) = explode(',', $node);
	$attrs = array(
		'id' => $ref,
		'lat' => sprintf('%1.7f', $lat),
		'lon' => sprintf('%1.7f', $lon),
	);
	$attributes = attrs($attrs);
	if (!isset($nodetags[$ref])) {
		echo sprintf('<node %s />', $attributes), "\n";
	} else {
		echo sprintf('<node %s>', $attributes), "\n";
		print_tags($nodetags[$ref]);
		echo '</node>', "\n";
	}
}

foreach ($ways as $way) {
	echo sprintf('<way %s >', attrs($way['attr'])), "\n";
	foreach ($way['nd'] as $ref) {
		echo sprintf('<nd ref="%s" />', $ref), "\n";
	}
	print_tags($way['tags']);
	echo '</way>', "\n";
	
}

foreach ($rels as $rel) {
	if (isset($rel['deleted'])) continue;
	echo sprintf('<relation %s >', attrs($rel['attr'])), "\n";
	foreach ($rel['members'] as $member) {
		echo sprintf('<member %s />', attrs($member)), "\n";
	}
	print_tags($rel['tags']);
	echo '</relation>', "\n";
}

echo '</osm>', "\n";

} catch (Exception $e) {

	header('HTTP/1.1 400 Bad Request');
	echo $e->getMessage();	

}

function allow_download ($user, $password) {

	if ($user == '') return false;
	if ($password == '') return false;

	$cryptpass = substr(crypt(strtolower($password), PASSWORD_SALT), 2);
	$sql_user = "SELECT id, userpasswd, uids, user_ids, allow_turistautak_region_download FROM geocaching.users WHERE member='" . addslashes($user) . "'";

	if (!$myrow_user = mysql_fetch_array(mysql_query($sql_user))) return false;
	if ($myrow_user['userpasswd'] != $cryptpass) return false;
	if ($myrow_user['allow_turistautak_region_download']) return true;
	
	$sql_rights = sprintf("SELECT COUNT(*) FROM regions_explicit WHERE user_id=%d AND allow_region_download=1", $myrow_user['id']);
	if (!simple_query($sql_rights)) return false;
	
	return true;

}

function line_type ($code) {

	$codes = array(
		0x0000 => 'nullás kód, általában elfelejtett típus',
		0x0081 => 'csapás',
		0x0082 => 'ösvény',
		0x0083 => 'gyalogút',
		0x0084 => 'szekérút',
		0x0085 => 'földút',
		0x0086 => 'burkolatlan utca',
		0x0087 => 'makadámút',
		0x0091 => 'burkolt gyalogút',
		0x0092 => 'kerékpárút',
		0x0093 => 'utca',
		0x0094 => 'kiemelt utca',
		0x0095 => 'országút',
		0x0096 => 'másodrendű főút',
		0x0097 => 'elsőrendű főút',
		0x0098 => 'autóút',
		0x0099 => 'autópálya',
		0x009a => 'erdei aszfalt',
		0x009b => 'egyéb közút',
		0x00a1 => 'lehajtó',
		0x00a2 => 'körforgalom',
		0x00a3 => 'lépcső',
		0x00a4 => 'kifutópálya',
		0x00b1 => 'folyó',
		0x00b2 => 'patak',
		0x00b3 => 'időszakos patak',
		0x00b4 => 'komp',
		0x00b5 => 'csatorna',
		0x00c1 => 'vasút',
		0x00c2 => 'kisvasút',
		0x00c3 => 'villamos',
		0x00c4 => 'kerítés',
		0x00c5 => 'elektromos vezeték',
		0x00c6 => 'csővezeték',
		0x00c7 => 'kötélpálya',
		0x00d1 => 'felmérendő utak',
		0x00d2 => 'kanyarodás tiltás',
		0x00d3 => 'vízpart',
		0x00d4 => 'völgyvonal',
		0x00d5 => 'megyehatár',
		0x00d6 => 'országhatár',
		0x00d7 => 'alapszintvonal',
		0x00d8 => 'főszintvonal',
		0x00d9 => 'vastag főszintvonal',
		0x00da => 'felező szintvonal',
	);
	
	return @$codes[$code];
}

function polygon_type ($code) {
	$codes = array(
		0x81 => 'erdő',
		0x82 => 'fenyves',
		0x83 => 'fiatalos',
		0x84 => 'erdőirtás',
		0x85 => 'bokros',
		0x86 => 'szőlő',
		0x87 => 'gyümölcsös',
		0x88 => 'rét',
		0x89 => 'park',
		0x8a => 'szántó',
		0x80 => 'zöldfelület',
		0x91 => 'tenger',
		0x92 => 'tó',
		0x93 => 'folyó',
		0x94 => 'mocsár',
		0x95 => 'nádas',
		0x96 => 'dagonya',
		0x90 => 'víz',
		0xa1 => 'megyeszékhely',
		0xa2 => 'nagyváros',
		0xa3 => 'kisváros',
		0xa4 => 'nagyközség',
		0xa5 => 'falu',
		0xa6 => 'településrész',
		0xa0 => 'település',
		0xb1 => 'épület',
		0xb2 => 'parkoló',
		0xb3 => 'ipari terület',
		0xb4 => 'bevásárlóközpont',
		0xb5 => 'kifutópálya',
		0xb6 => 'sípálya',
		0xb7 => 'szánkópálya',
		0xb8 => 'golfpálya',
		0xb9 => 'sportpálya',
		0xba => 'temető',
		0xbb => 'katonai terület',
		0xbc => 'pályaudvar',
		0xbd => 'iskola',
		0xbe => 'kórház',
		0xb0 => 'mesterséges terület',
		0xf1 => 'fokozottan védett terület',
		0xf2 => 'háttér',
	);
	
	return @$codes[$code];
}

function burkolat ($code) {
	
	$codes = array(
		'aszfalt' => 'asphalt',
		'rossz aszfalt' => 'asphalt',
		'beton' => 'concrete',
		'makadám' => 'compacted',
		'köves' => 'gravel',
		'kavics' => 'pebblestone',
		'homok' => 'sand',
		'föld' => 'dirt',
		'középen füves' => 'grass',
		'fű' => 'grass',

		'térkő' => 'paving_stones',
		'murva' => 'gravel',
		'kockakő' => 'cobblestone',
		'zúzottkő' => 'gravel',
		'sziklás' => 'rock',
		'fa' => 'wood',
		'gumi' => 'tartan',
		'föld k. fű' => 'grass',
		'homok k. fű' => 'grass',
		'fold' => 'dirt',
		'agyag' => 'clay',
		'kisszemcsés-zúzottkő' => 'fine_gravel',
		'kissz. zúzott' => 'fine_gravel',
		'füves' => 'grass',
		'vasbeton útpanel' => 'concrete:plates',
		'kő lépcső' => '',
		'kavics-kő' => 'gravel',
		'kő' => 'gravel',
		'terméskő, kitöltött' => 'gravel',
		'macskakő' => 'cobblestone',
		'kavicsos-köves' => 'gravel',
		'idomkő, beton térkő' => 'paving_stones',
		'Földes' => 'dirt',
		'kőzúzalék' => 'gravel',
		'fém' => 'metal',
		'földes, kavicsos' => 'dirt',
		'rossz beton' => 'concrete',
		'palló' => '',
		'beton lépcső' => 'concrete',
		'utcakő' => 'cobblestone',
		'agyagos homok' => 'sand',
		'sóderos föld' => 'dirt',
		'Földes, középen füve' => '',
		'tönkrement aszfalt' => 'asphalt',

	);
	
	return @$codes[$code];

}

function JarhatosagAutoval ($code) {
	
	$codes = array(
		'A' => 'good',
		'B' => 'bad',
		'C' => 'very_bad',
		'D' => 'very_horrible',
	);
	
	return @$codes[$code];

}

function tr ($str) {
	return iconv('Windows-1250', 'UTF-8', $str);
}

function attrs ($arr) {
	$attrs = array();
	foreach ($arr as $k => $v) {
		$attrs[] = sprintf('%s="%s"', $k, htmlspecialchars($v));
	}
	return implode(' ', $attrs);
}

function print_tags ($tags) {
	foreach ($tags as $k => $v) {
		if (trim(@$v) == '') continue;
		echo sprintf('<tag k="%s" v="%s" />', htmlspecialchars(trim($k)), htmlspecialchars(trim($v))), "\n";
	}
}

function refs ($arr) {
	$out = array();
	foreach ($arr as $item) {
		$out[] = $item['ref'];
	}
	return $out;
}

function comment_types () {
	$myself = file_get_contents(__FILE__);
	$myself = preg_replace_callback('/(^\s*)case (0x[0-9a-f]+):\s*$/um', "comment_types_callback", $myself);
	header('Content-type: text/plain; charset=utf-8');
	echo $myself;
	exit;
}

function comment_types_callback ($matches) {
global $poi_types_array;
	if (preg_match('/^0x([0-9a-f]{4,4})$/', $matches[2], $regs)) {
		$code = hexdec($regs[1]);
		if ($code >= 0xa000) {
			// poi
			$typename = tr($poi_types_array[$code]['nev']);
		} else {
			// vonal
			$typename = line_type($code);
		}

	} else if (preg_match('/^0x([0-9a-f]{2,2})$/', $matches[2], $regs)) {
		// felület
		$code = hexdec($regs[1]);
		$typename = polygon_type($code);
	}		
			
	return sprintf('%scase %s: // %s', $matches[1], $matches[2], $typename);
}

function refFromNode ($node) {

	return '-' . str_replace('.', '', str_replace(',', '', $node));

}
