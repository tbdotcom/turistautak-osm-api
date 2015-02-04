<?php 	

/*
 * TUHU tipuskod - OSM tag-ek megfeleltetes 
 * Zelena Endre <gps@turablog.com> 2015.02.04.
 *
 */

	$dekod=array (
		'0xa006' => array ( 'place'           => 'suburb'                 ), // településrész
		'0xa101' => array ( 'shop'            => 'convenience'            ), // élelmiszerbolt
		'0xa102' => array ( 'shop'            => 'mall'                   ), // bevásárlóközpont
		'0xa103' => array ( 'amenity'         => 'restaurant'             ), // étterem
		'0xa104' => array ( 'amenity'         => 'fast_food'              ), // büfé
		'0xa105' => array ( 'amenity'         => 'pub'                    ), // kocsma
                '0xa106' => array ( 'amenity'         => 'cafe'                   ), // kávézó
                '0xa107' => array ( 'shop'            => 'confectionery'          ), // cukrászda
                '0xa108' => array ( 'craft'           => 'winery'                 ), // pincészet
                '0xa109' => array ( 'amenity'         => 'fast_food'              ), // gyorsétterem
                '0xa10a' => array ( 'shop'            => 'bakery'                 ), // pékség
                '0xa10b' => array ( 'shop'            => 'greengrocer'            ), // zöldség-gyümölcs
                '0xa10c' => array ( 'shop'            => 'butcher'                ), // hentes
                '0xa201' => array ( 'natural'         => 'water'                  ), // tó
                '0xa202' => array ( 'natural'         => 'spring'                 ), // forrás
                '0xa203' => array ( 'natural'         => 'spring',
                                    'intermittent'    => 'yes'                    ), // időszakos forrás
                '0xa205' => array ( 'amenity'         => 'drinking_water'         ), // közkút
                '0xa206' => array ( 'disused:amenity' => 'drinking_water'         ), // elzárt közkút
                '0xa207' => array ( 'emergency'       => 'fire_hydrant'           ), // tűzcsap
                '0xa208' => array ( 'amenity'         => 'fountain'               ), // szökőkút
                '0xa300' => array ( 'building'        => 'yes'                    ), // épület
                '0xa301' => array ( 'building'        => 'yes'                    ), // ház
                '0xa302' => array ( 'tourism'         => 'museum'                 ), // múzeum
                '0xa303' => array ( 'amenity'         => 'place_of_worship',
                                    'building'        => 'church'                 ), // templom
	        '0xa304' => array ( 'building'        => 'chapel'                 ), // kápolna
	        '0xa305' => array ( 'amenity'         => 'place_of_worship',
                                    'religion'        => 'jewish'                 ), // zsinagóga
	        '0xa306' => array ( 'amenity'         => 'school'                 ), // iskola
	        '0xa307' => array ( 'historic'        => 'castle'                 ), // vár
	        '0xa308' => array ( 'historic'        => 'castle'                 ), // kastély
	        '0xa401' => array ( 'tourism'         => 'hotel'                  ), // szálloda
	        '0xa400' => array ( 'tourism'         => 'guest_house'            ), // szállás
	        '0xa402' => array ( 'tourism'         => 'guest_house'            ), // panzió
	        '0xa403' => array ( 'tourism'         => 'guest_house'            ), // magánszállás
	        '0xa405' => array ( 'tourism'         => 'guest_house'            ), // turistaszállás
	        '0xa404' => array ( 'tourism'         => 'camp_site'              ), // kemping
	        '0xa406' => array ( 'tourism'         => 'chalet'                 ), // kulcsosház
	        '0xa501' => array ( 'historic'        => 'memorial', 
                                    'memorial'        => 'plaque'                 ), // emléktábla
	        '0xa502' => array ( 'historic'        => 'wayside_cross'          ), // kereszt
	        '0xa503' => array ( 'historic'        => 'memorial'               ), // emlékmű
	        '0xa504' => array ( 'tourism'         => 'artwork'                ), // szobor
	        '0xa506' => array ( 'historic'        => 'wayside_shrine'         ), // sír
	        '0xa602' => array ( 'highway'         => 'bus_stop'               ), // buszmegálló
	        '0xa603' => array ( 'railway'         => 'tram_stop'              ), // villamosmegálló
	        '0xa604' => array ( 'railway'         => 'station'                ), // pályaudvar
	        '0xa605' => array ( 'railway'         => 'station'                ), // vasútállomás
	        '0xa606' => array ( 'railway'         => 'halt'                   ), // vasúti megálló
	        '0xa607' => array ( 'barrier'         => 'border_control'         ), // határátkelőhely
	        '0xa608' => array ( 'amenity'         => 'ferry_terminal'         ), // komp
	        '0xa60a' => array ( 'amenity'         => 'ferry_terminal'         ), // hajóállomás
	        '0xa609' => array ( 'leisure'         => 'marina'                 ), // kikötő
	        '0xa60b' => array ( 'aeroway'         => 'areodrome'              ), // repülőtér
	        '0xa60e' => array ( 'highway'         => 'speed_camera'           ), // traffipax
	        '0xa60f' => array ( 'amenity'         => 'bus_station'            ), // buszpályaudvar
		'0xa610' => array ( 'railway'         => 'level_crossing'         ), // vasúti átjáró
		'0xa611' => array ( 'highway'         => 'motorway_junction'      ), // autópálya-csomópont
		'0xa612' => array ( 'amenity'         => 'taxi'                   ), // taxiállomás
		'0xa701' => array ( 'shop'            => 'yes'                    ), // üzlet
		'0xa702' => array ( 'amenity'         => 'atm'                    ), // bankautomata
		'0xa703' => array ( 'amenity'         => 'bank'                   ), // bankfiók
		'0xa704' => array ( 'amenity'         => 'fuel'                   ), // benzinkút
		'0xa705' => array ( 'amenity'         => 'hospital'               ), // kórház
		'0xa706' => array ( 'amenity'         => 'doctors'                ), // orvosi rendelő
		'0xa707' => array ( 'amenity'         => 'pharmacy'               ), // gyógyszertár
		'0xa708' => array ( 'office'          => 'government'             ), // hivatal
		'0xa709' => array ( 'internet_access' => 'wlan'                   ), // hotspot
		'0xa70a' => array ( 'amenity'         => 'telephone'              ), // nyilvános telefon
		'0xa70b' => array ( 'amenity'         => 'parking'                ), // parkoló
		'0xa70c' => array ( 'amenity'         => 'post_office'            ), // posta
		'0xa70d' => array ( 'amenity'         => 'post_box'               ), // postaláda
		'0xa70f' => array ( 'amenity'         => 'police'                 ), // rendőrség
		'0xa710' => array ( 'amenity'         => 'fire_station'           ), // tűzoltóság
		'0xa711' => array ( 'emergency'       => 'ambulance_station'      ), // mentőállomás
		'0xa712' => array ( 'shop'            => 'car_repair'             ), // autószerviz
		'0xa713' => array ( 'shop'            => 'bicycle'                ), // kerékpárbolt
		'0xa714' => array ( 'amenity'         => 'toilets'                ), // wc
		'0xa717' => array ( 'amenity'         => 'marketplace'            ), // piac
		'0xa718' => array ( 'tourism'         => 'information'            ), // turistainformáció
		'0xa71c' => array ( 'amenity'         => 'bureau_de_change'       ), // pénzváltó
		'0xa806' => array ( 'leisure'         => 'pitch',
		                    'sport'           => 'tennis'                 ), // teniszpálya
		'0xa809' => array ( 'sport'           => 'swimming'               ), // uszoda
		'0xa810' => array ( 'leisure'         => 'pitch'                  ), // sportpálya
		'0xa901' => array ( 'amenity'         => 'theatre'                ), // színház
		'0xa902' => array ( 'amenity'         => 'cinema'                 ), // mozi
		'0xa903' => array ( 'amenity'         => 'library'                ), // könyvtár
		'0xa905' => array ( 'tourism'         => 'zoo'                    ), // állatkert
		'0xa908' => array ( 'tourism'         => 'attraction'             ), // látnivaló
		'0xaa03' => array ( 'man_made'        => 'works'                  ), // gyár
		'0xaa06' => array ( 'man_made'        => 'tower',
		                    'tower_type'      => 'communication'          ), // rádiótorony
		'0xaa07' => array ( 'man_made'        => 'chimney'                ), // kémény
		'0xaa08' => array ( 'man_made'        => 'water_tower'            ), // víztorony
		'0xaa0a' => array ( 'amenity'         => 'shelter'                ), // esőház
		'0xaa0c' => array ( 'information'     => 'board'                  ), // információs tábla
		'0xaa0e' => array ( 'barrier'         => 'gate'                   ), // kapu
		'0xaa0f' => array ( 'man_made'        => 'tower',
		                    'tower_type'      => 'observation',
		                    'tourism'         => 'viewpoint'              ), // kilátó
		'0xaa10' => array ( 'amenity'         => 'hunting_stand'          ), // magasles
		'0xaa11' => array ( 'tourism'         => 'picnic_site'            ), // pihenőhely
		'0xaa12' => array ( 'amenity'         => 'bench'                  ), // pad
		'0xaa13' => array ( 'fireplace'       => 'yes'                    ), // tűzrakóhely
		'0xaa14' => array ( 'barrier'         => 'lift_gate'              ), // sorompó
		'0xaa16' => array ( 'man_made'        => 'survey_point'           ), // háromszögelési pont
		'0xaa17' => array ( 'historic'        => 'boundary_stone'         ), // határkő
		'0xaa2a' => array ( 'highway'         => 'milestone'              ), // km-/útjelzőkő
		'0xaa2b' => array ( 'ruins'           => 'yes'                    ), // rom
		'0xaa2d' => array ( 'man_made'        => 'tower'                  ), // torony
		'0xaa34' => array ( 'man_made'        => 'water_works'            ), // vízmű
		'0xaa36' => array ( 'power'           => 'transformer'            ), // transzformátor
		'0xaa37' => array ( 'leisure'         => 'playground'             ), // játszótér
		'0xab02' => array ( 'natural'         => 'tree'                   ), // fa
		'0xab03' => array ( 'ford'            => 'yes'                    ), // gázló
		'0xab04' => array ( 'natural'         => 'mud'                    ), // dagonya
		'0xab05' => array ( 'natural'         => 'tree'                   ), // geofa
		'0xab06' => array ( 'barrier'         => 'yes'                    ), // akadály
		'0xab07' => array ( 'natural'         => 'cave_entrance'          ), // barlang
		'0xab0a' => array ( 'natural'         => 'peak'                   ), // magaslat
		'0xab0b' => array ( 'tourism'         => 'viewpoint'              ), // kilátás
		'0xab0c' => array ( 'natural'         => 'cliff'                  ), // szikla
		'0xab0d' => array ( 'waterway'        => 'waterfall'              ), // vízesés
		'0xac02' => array ( 'amenity'         => 'recycling'              ), // szelektív hulladékgyűjtő
		'0xac03' => array ( 'amenity'         => 'waste_transfer_station' ), // hulladéklerakó
		'0xac04' => array ( 'amenity'         => 'waste_basket'           ), // hulladékgyűjtő
		'0xac05' => array ( 'amenity'         => 'waste_disposal'         ), // konténer
		'0xad01' => array ( 'checkpoint'      => 'hiking',
		                    'checkpoint:type' => 'stamp'                  ), // pecsételőhely
		'0xae01' => array ( 'place'           => 'locality'               ), // névrajz
		'0xae06' => array ( 'noi'             => 'yes',
		                    'hiking'          => 'yes',
		                    'tourism'         => 'information',
		                    'information'     => 'route_marker'           ), // turistaút csomópont, szakértője: modras

	       );

	function poiCodeToOSM ($code) {
		$tags = @$dekod($code);
		if ($tags === null) return array();
		return $tags;
	}

