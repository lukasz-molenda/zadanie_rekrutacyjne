<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use League\Csv\Reader;
use League\Csv\Writer;
use AppBundle\Entity\Csv;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }

    /**
     * @Route("/import_csv", name="import_csv")
     */
    public function importCsvAction(Request $request)
    {
      $dir = $this->get('kernel')->getRootDir() . '/../web/uploads/csv/';
      $name = uniqid() . '.csv';

      foreach ($request->files as $uploadedFile) {
          $uploadedFile->move($dir, $name);
      }

      $file = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $name;
      $reader = Reader::createFromPath($file);
      $header = $reader->fetchOne();
      if (count($header) < 2) {
        $request->getSession()->getFlashBag()->add('danger', 'Plik .csv musi mieć co najmniej 2 kolumny');
        return $this->redirect('/');
      } elseif (count($header) > 50) {
        $request->getSession()->getFlashBag()->add('danger', 'Plik. csv nie może mieć więcej niż 50 kolumn');
        return $this->redirect('/');
      } else {
        $results = $reader->fetchAssoc();
        return $this->render('default/mapping_fields.html.twig', ['results' => $results, 'name' => $name]);
      }
    }

    /**
     * @Route("/store_records", name="store_records")
     */
    public function storeRecordsAction(Request $request)
    {
      if (!ini_get("auto_detect_line_endings")) {
          ini_set("auto_detect_line_endings", '1');
      }
      $data = $request->request->all();
      if (!in_array('GivenName', $data) || !in_array('Username', $data) || !in_array('Surname', $data)) {
        $request->getSession()->getFlashBag()->add('danger', 'Brak któregoś z wymaganych pól.');
        return $this->redirect('/');
      }

      $name = $data['name'];
      $file = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $name;
      $reader = Reader::createFromPath($file);
      $header = $reader->fetchOne();

      $temp = [];

      // Create array with keys mapped by user
      foreach($header as $result) {
        if (array_key_exists($result, $data)) {
          $temp[$result] = $data[$result];
        } else {
          $temp[$result] = $result;
        }
      }

      // Trim BOMs from $temp array keys
      foreach ($temp as $key => $value) {
        $newKey = trim(json_encode($key));
        $newKey = str_replace('\ufeff', '', $newKey);
        $newKey = json_decode($newKey);
        unset($temp[$key]);
        $temp[$newKey] = json_decode(str_replace('\ufeff', '', json_encode($value)));
      }

      function check_if_value_exists($ownValue, $temp)
      {
        foreach ($temp as $key => $value) {
          if ($value == $ownValue) {
            return true;
          }
        }
      }

      $results = $reader->fetchAssoc();

      $time_start = microtime(true);

      $counter = 0;
      $index = 0;

      foreach ($results as $row) {
        $csv = new Csv();
        $index += 1;

          // Trim BOMs from $row array keys
          foreach ($row as $key => $value) {
            $newKey = trim(json_encode($key));
            $newKey = str_replace('\ufeff', '', $newKey);
            $newKey = json_decode($newKey);
            unset($row[$key]);
            $row[$newKey] = str_replace('\ufeff', '', $value);
          }


          // var_dump($temp);

          // Mapping keys on selected by user
          if ((check_if_value_exists('GivenName', $temp) == false) || (check_if_value_exists('Surname', $temp) == false) || (check_if_value_exists('Username', $temp) == false)) {
            $counter += 1;
          } else {
            $row['Number'] ? $csv->{'set'.$temp['Number']}($row['Number']) : null;
            $row['Gender'] ? $csv->{'set'.$temp['Gender']}($row['Gender']) : null;
            $row['NameSet'] ? $csv->{'set'.$temp['NameSet']}($row['NameSet']) : null;
            $row['Title'] ? $csv->{'set'.$temp['Title']}($row['Title']) : null;
            $csv->{'set'.$temp['GivenName']}($row['GivenName']);
            $row['MiddleInitial'] ? $csv->{'set'.$temp['MiddleInitial']}($row['MiddleInitial']) : null;
            $csv->{'set'.$temp['Surname']}($row['Surname']);
            $row['StreetAddress'] ? $csv->{'set'.$temp['StreetAddress']}($row['StreetAddress']) : null;
            $row['City'] ? $csv->{'set'.$temp['City']}($row['City']) : null;
            $row['State'] ? $csv->{'set'.$temp['State']}($row['State']) : null;
            $row['ZipCode'] ? $csv->{'set'.$temp['ZipCode']}($row['ZipCode']) : null;
            $row['Country'] ? $csv->{'set'.$temp['Country']}($row['Country']) : null;
            $row['EmailAddress'] ? $csv->{'set'.$temp['EmailAddress']}($row['EmailAddress']) : null;
            $csv->{'set'.$temp['Username']}($row['Username']);
            $row['Password'] ? $csv->{'set'.$temp['Password']}($row['Password']) : null;
            $row['BrowserUserAgent'] ? $csv->{'set'.$temp['BrowserUserAgent']}($row['BrowserUserAgent']) : null;

            $em = $this->getDoctrine()->getManager();

            $em->persist($csv);
          }
      }
            if (isset($em)) {
              $em->flush();
            }

            $time_end = microtime(true);
            $time = $time_end - $time_start;

            $time = round($time, 2);
            $incorrect = $counter;
            $correct = $index - $counter;

            return $this->render('default/summary.html.twig', ['time' => $time, 'correct' => $correct, 'incorrect' => $incorrect]);
    }

}
