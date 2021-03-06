<?php

namespace PCBIS2PDF\Providers;

use PCBIS2PDF\ProviderAbstract;

use a;
use str;

/**
 * Class KNV
 *
 * Holds functions to collect & process KNV gibberish to useful information
 *
 * @package PCBIS2PDF\Providers
 */

class KNV extends ProviderAbstract
{
    /**
     * Returns raw book data from KNV
     *
     * .. if book for given ISBN exists
     *
     * @param String $isbn
     * @return Array
     */
    public function getBook($isbn)
    {
        $json = file_get_contents(basename('./knv.login.json'));
        $login = json_decode($json, true);

        $client = new \SoapClient('http://ws.pcbis.de/knv-2.0/services/KNVWebService?wsdl', [
            'soap_version' => SOAP_1_2,
            'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'trace' => true,
            'exceptions' => true,
        ]);

        // For getting started with KNV's (surprisingly well documented) german API,
        // see http://www.knv.de/fileadmin/user_upload/IT/KNV_Webservice_2018.pdf
        $query = $client->WSCall([
            // Login using credentials provided by `knv.login.json`
    				'LoginInfo' => $login,
            // Starting a new database query
            'Suchen' => [
                'Datenbank' => [
                // Basically searching all databases they got
                    'KNV',
                    'KNVBG',
                    'BakerTaylor',
                    'Gardners',
                ],
                'Suche' => [
                    'SimpleTerm' => [
                        // Simple search suffices as from exported CSV,
                        // we already know they know .. you know?
                        'Suchfeld' => 'ISBN',
                        'Suchwert' => $isbn,
                        'Schwert2' => '',
                        'Suchart' => 'Genau'
                    ],
                ],
            ],
            // Reading the results of the query above
            'Lesen' => [
                // Returning the first result is alright, since given ISBN is unique
                'SatzVon' => 1,
                'SatzBis' => 1,
    						'Format' => 'KNVXMLLangText',
                'AuswahlMultimediaDaten' => [
                    // We only want the best cover they got - ZOOM mode ON!
                    'mmDatenLiefern' => true,
                    'mmVarianteFilter' => 'zoom',
                ],
            ],
            // .. and logging out, that's it!
            'Logout' => true
        ]);

        // Getting raw XML response & preparing it to be loaded by SimpleXML
    		$result = $query->Daten->Datensaetze->Record->ArtikelDaten;
    		$result = str::replace($result, '&', '&amp;');

        // XML to JSON to PHP array - we want its last entry
    		$xml = simplexml_load_string($result);
    		$json = json_encode($xml);
    		$array = (json_decode($json, true));

    		return a::last($array);
    }


    /**
     * Returns subtitle from KNV
     *
     * .. if it exists
     *
     * @param Array $array
     * @return String
     */
    private function getAuthor($array, $arrayCSV = ['Titel' => ''])
    {
    		if (a::missing($array, ['AutorSachtitel'])) {
    				return '';
    		}

    		if ($arrayCSV['Titel'] == $array['AutorSachtitel']) {
    				return '';
    		}

    		return $array['AutorSachtitel'];
    }


    /**
     * Returns subtitle from KNV
     *
     * .. if it exists
     *
     * @param Array $array
     * @return String
     */
    private function getSubtitle($array)
    {
        if (a::missing($array, ['Utitel'])) {
            return '';
        }

    		if ($array['Utitel'] == null) {
    		    return '';
        }

        return $array['Utitel'];
    }


    private function getYear($array)
    {
        if (a::missing($array, ['Erschjahr'])) {
            return '';
        }

        return $array['Erschjahr'];
    }


    /**
     * Returns descriptive text from KNV
     *
     * .. if it exists
     *
     * @param Array $array
     * @return String
     */
    private function getText($array)
    {
        if (a::missing($array, ['Text1'])) {
            return 'Keine Beschreibung vorhanden!';
        }

        $textArray = str::split($array['Text1'], 'º');

        foreach ($textArray as $index => $entry) {
            $entry = htmlspecialchars_decode($entry);
            $entry = str::replace($entry, '<br><br>', '. ');
            $entry = str::unhtml($entry);
            $textArray[$index] = $entry;

            if (str::length($textArray[$index]) < 130 && count($textArray) > 1) {
                unset($textArray[array_search($entry, $textArray)]);
            }
        }
        return a::first($textArray);
    }


    /**
     * Returns participant(s) from KNV
     *
     * .. if it/they exist(s)
     *
     * @param Array $array
     * @return String
     */
    private function getParticipants($array)
    {
    		if (a::missing($array, ['Mitarb'])) {
    			return '';
    		}

    		return $array['Mitarb'];
    }


    /**
     * Returns book dimensions from KNV
     *
     * .. if width & height exist
     *
     * @param Array $array
     * @return String
     */
    private function convertMM($string)
    {
    		$string = $string / 10;
    		$string = str::replace($string, '.', ',');

    		return $string . 'cm';
    }


    private function getDimensions($array)
    {
    		if (a::missing($array, ['Breite'])) {
    				return '';
    		}

    		if (a::missing($array, ['Hoehe'])) {
    				return '';
    		}

    		$width = $this->convertMM($array['Breite']);
    		$height = $this->convertMM($array['Hoehe']);

    		return $width . ' x ' . $height;
    }


    /**
     * Returns cover URL from KNV
     *
     * .. always!
     *
     * @param Array $array
     * @return String
     */
    private function getCover($array)
    {
    		return $array['MULTIMEDIA']['MMUrl'];
    }

    /**
     * Enriches an array with KNV information
     *
     * @param Array $dataInput - Input that should be processed
     * @return Array
     */
    public function process(array $dataInput = null)
    {
        if ($dataInput == null) {
            throw new \Exception('No data to process!');
        }

        $dataOutput = [];

        foreach ($dataInput as $array) {
        		try {
        		    $book = $this->accessCache($array['ISBN'], 'KNV');
        		    $arrayKNV = [
        						'Erscheinungsjahr' => $this->getYear($book),
        		        'AutorIn' => $this->getAuthor($book, $array),
        		        'Untertitel' => $this->getSubtitle($book),
        						'Abmessungen' => $this->getDimensions($book),
        		        'Mitwirkende' => $this->getParticipants($book),
        		        'Inhaltsbeschreibung' => $this->getText($book),
        		        'Cover KNV' => $this->getCover($book),
        		    ];
        		} catch (Exception $e) {
        		    echo 'Error: ' . $e->getMessage();
        		}

        		$array = a::update($array, array_filter($arrayKNV, 'strlen'));

            $dataOutput[] = $this->sortArray($array);
        }

        return $dataOutput;
    }
}
