<?php

/**
 * PCBIS2PDF - Funny subtitle
 *
 * @link https://github.com/Fundevogel/pcbis2pdf
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GPL v3
 */

namespace PCBIS2PDF;

use a;
use str;

/**
 * Class BookRecommendations
 *
 * Funny description
 *
 * @package PCBIS2PDF
 */

class PCBIS2PDF
{

    /**
     * Current version number of BookRecommendations
     */
    const VERSION = '1.0.0';

    public function __construct()
    {
        $this->translations = json_decode(file_get_contents(__DIR__ . '/../languages/de.json'), true);

        /**
         * CSV file headers in order of use when exporting with pcbis.de
         *
         * @var array
         */
        $this->headers = [
            // 'category', /* 2 */
            'AutorIn',
            'Titel',
            'Verlag',
            'ISBN',
            'Einband',
            'Preis',
            'a',
            'b',
            'c',
            'Informationen',
            'Zusatz',
            'Kommentar'
        ];
    }

    // public function setHeaders($headers)
    // {
    //     $this->headers = $headers;
    // }
    //
    // public function getHeaders()
    // {
    //     return $this->headers;
    // }


    /**
     * Merges CSV files
     *
     * @param String $input - Source CSV files to read data from
     * @param String $output - Source CSV files to write data to
     * @param String $delimiter - Delimiting character (optional)
     * @return Array
     */
    public function mergeCSV(string $input = './src/csv/*.csv', string $output = './src/Titelexport.csv')
    {
        $count = 0;

        foreach (glob($input) as $file) {
            if (($handle = fopen($file, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    $rowCount = count($row);
                    $array[$count][] = $file;
                    unset($array[$count][0]);

                    for ($i = 0; $i < $rowCount; $i++) {
                        $array[$count][] = $row[$i];
                    }
                    $count++;
                }
                fclose($handle);
            }
        }

        $handle = fopen($output, 'w');

        foreach ($array as $fields) {
            fputcsv($handle, $fields, ';');
        }

        fclose($handle);
    }


    /**
     * Turns CSV data into a PHP array
     *
     * @param String $input - Source CSV file to read data from
     * @param String $delimiter - Delimiting character (optional)
     * @return Array
     */
    public function CSV2PHP(string $input = './src/Titelexport.csv', string $delimiter = ';')
    {
        if ($input == null) {
            $input = $this->input;
        }

        if (!file_exists($input) || !is_readable($input)) {
            return false;
        }

        $data = [];

        if (($handle = fopen($input, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $row = array_map('utf8_encode', $row);
                $data[] = array_combine($this->headers, $row);
            }
            fclose($handle);
        }
        return $data;
    }


    /**
     * Turns a PHP array into CSV file
     *
     * @param Array $data - Destination CSV file to write data to
     * @param String $output - Destination CSV file to write data to
     * @param String $delimiter - Delimiting character (optional)
     * @return Stream
     */
    public function PHP2CSV(array $dataInput, string $output = './dist/data.csv', string $delimiter = ';')
    {
        $header = null;

        if (($handle = fopen($output, 'w')) !== false) {
            foreach ($dataInput as $row) {
                if (!$header) {
                    fputcsv($handle, array_keys($row), $delimiter);
                    $header = true;
                }
                fputcsv($handle, $row, $delimiter);
            }
            fclose($handle);
        }
        return true;
    }


    private function generateInfo($array)
    {
    		$age = 'Keine Altersangabe';
    		$pageCount = '';
    		$year = '';

    		foreach ($array as $entry) {
    				// Remove garbled book dimensions
    				if (str::contains($entry, ' cm') || str::contains($entry, ' mm')) {
    						// unset($array[$index]);
    						unset($array[array_search($entry, $array)]);
    				}

    				// Filtering age
    				if (str::contains($entry, ' J.') || str::contains($entry, ' Mon.')) {
    						$age = $this->convertAge($entry);
    						// unset($array[$index]);
    						unset($array[array_search($entry, $array)]);
    				}

    				// Filtering page count
    				if (str::contains($entry, ' S.')) {
    						$pageCount = $this->convertPageCount($entry);
    						// unset($array[$index]);
    						unset($array[array_search($entry, $array)]);
    				}

    				// Filtering year (almost always right at this point)
    				if (str::length($entry) == 4) {
    						$year = $entry;
    						// unset($array[$index]);
    						unset($array[array_search($entry, $array)]);
    				}
    		}

    		$strings = $this->translations['information'];
    		$array = str::replace($array,
      			array_keys($strings),
      			array_values($strings)
    		);

    		$info = ucfirst(implode(', ', $array));

    		if (str::length($info) > 0) {
      			$info = str::replace($info, '.', '') . '.';
    		}

    		return [
      			$info,
      			$year,
      			$age,
      			$pageCount,
    		];
    }

    private function convertTitle($string)
    {
    		// Input: Book title.
    		// Output: Book title
    		return str::substr($string, 0, -1);
    }


    private function convertAge($string)
    {
      	$string = str::replace($string, 'J.', 'Jahren');
      	$string = str::replace($string, 'Mon.', 'Monaten');
      	$string = str::replace($string, '-', ' bis ');
      	$string = str::replace($string, 'u.', '&');

      	return $string;
    }


    private function convertPageCount($string)
    {
    		return (int) $string;
    }


    private function convertBinding($string)
    {
    		$translations = $this->translations['binding'];
    		$string = $translations[$string];

    		return $string;
    }


    private function convertPrice($string)
    {
    		// Input: XX.YY EUR
    		// Output: XX,YY €
    		$string = str::replace($string, 'EUR', '€');
    		$string = str::replace($string, '.', ',');

    		return $string;
    }


    public function downloadCover(string $isbn, string $fileName = null)
    {
        if ($fileName == null) {
            $fileName = $isbn;
        }

        $file = './dist/images/' . $fileName . '.jpg';

        if (file_exists($file)) {
            echo 'Book cover for ' . $isbn . ' already exists, skipping ..' . "\n";
            return true;
        }

        $url = 'https://portal.dnb.de/opac/mvb/cover.htm?isbn=' . $isbn;

        if ($handle = fopen($file, 'w')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            $result = parse_url($url);
            curl_setopt($ch, CURLOPT_REFERER, $result['scheme'] . '://' . $result['host']);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0');
            $raw = curl_exec($ch);
            curl_close($ch);

            if (!$raw) {
                @unlink($file);
                return false;
            }

            fwrite($handle, $raw);
            fclose($handle);

            return true;
        }
        return false;
    }


    /**
     * Enriches an array with specific provider information
     *
     * @param Array $dataInput - Input that should be processed
     * @return Array
     */
    public function process(array $dataInput = null)
    {
        if ($dataInput == null) {
            $dataInput = $this->CSV2PHP();
        }

        $dataOutput = [];

        foreach ($dataInput as $array) {
            // Gathering & processing generic book information
            $infoString = $array['Informationen'];
            $infoArray = str::split($infoString, ';');

            if (count($infoArray) == 1) {
                $infoArray = str::split($infoString, '.');
            }

            // Extracting variables from $infoArray
            list(
                $info,
                $year,
                $age,
                $pageCount
            ) = $this->generateInfo($infoArray);

            // Title, cover & image download
            $title = $this->convertTitle($array['Titel']);
            $slug = str::slug($title);

            $hasCover = $this->downloadCover($array['ISBN'], $slug);
            $cover = $hasCover ? $slug . '.jpg' : '';
            $coverDNB = $hasCover ? 'https://portal.dnb.de/opac/mvb/cover.htm?isbn=' . $array['ISBN'] : '';

            $array = a::update($array, [
                'Einband' => $this->convertBinding($array['Einband']),
                'Preis' => $this->convertPrice($array['Preis']),
                'Titel' => $title,
                'Untertitel' => '',
                'Altersempfehlung' => $age,
                'Erscheinungsjahr' => $year,
                'Seitenzahl' => $pageCount,
                'Abmessungen' => '',
                'Mitwirkende' => '',
                'Informationen' => $info,
                'Inhaltsbeschreibung' => '',
                'Cover' => $cover,
                'Cover DNB' => $coverDNB,
                'Cover KNV' => '',
            ]);

            $data[] = $array;
        }

        $providers = array_map(function ($filePath) {
            $fileName = basename($filePath, '.php');
            return $fileName;
        }, glob(__DIR__ . '/Providers/*.php'));

        try {
            foreach ($providers as $provider) {
                $providerName = ucfirst($provider);
                $className = 'PCBIS2PDF\\Providers\\' . $providerName;

                if (!class_exists($className)) {
                    continue;
                }

                $classObject = new $className($data);

                if (!$classObject instanceof ProviderAbstract || !is_callable([$classObject, 'process'])) {
                    continue;
                }

                $dataOutput = call_user_func([$classObject, 'process']);

                if ($dataOutput) {
                    echo 'Operation was successful!' . "\n";
                    break;
                }
            }
            return $dataOutput;
        } catch (\Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }
}