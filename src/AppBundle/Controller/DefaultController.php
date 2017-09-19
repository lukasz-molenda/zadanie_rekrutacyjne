<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use League\Csv\Reader;
use League\Csv\Writer;
use AppBundle\Entity\Csv;
use AppBundle\Form\CsvType;
use AppBundle\Form\MapFieldsType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

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

      $em = $this->getDoctrine()->getManager();
      $fieldNames = array_flip($em->getClassMetadata('AppBundle\Entity\Csv')->getFieldNames());
      foreach ($fieldNames as $key => $value) {
        $fieldNames[$key] = $key;
      }

      // Creating form with raw value from .csv
      $form1 = $this->createForm(MapFieldsType::class, $header, ['action' => $this->generateUrl('map_fields', ['csv_name' => $name]), 'data' => ['header' => $header, 'fieldNames' => $fieldNames]]);
      $form1->handleRequest($request);

      if (count($header) < 2) {
        $request->getSession()->getFlashBag()->add('danger', 'Plik .csv musi mieć co najmniej 2 kolumny');
        return $this->redirect('/');
      } elseif (count($header) > 50) {
        $request->getSession()->getFlashBag()->add('danger', 'Plik. csv nie może mieć więcej niż 50 kolumn');
        return $this->redirect('/');
      } else {
        $results = $reader->fetchAssoc();
        return $this->render('default/mapping_fields.html.twig', ['results' => $results, 'name' => $name, 'form1' => $form1->createView()]);
      }
    }

    /**
     * @Route("/map_fields/{csv_name}", name="map_fields")
     */
    public function mapFieldsAction(Request $request, $csv_name)
    {
      $data = $request->request->get('map_fields');
      $name = $csv_name;
      $file = $this->get('kernel')->getRootDir() . "/../web/uploads/csv/" . $name;
      $reader = Reader::createFromPath($file);
      $results = $reader->fetchAssoc();

      $time_start = microtime(true);
      $counter = 0;
      $index = 0;

      // Creating new array with mapped fields
      $new_arr = [];
      foreach ($results as $row) {
        $temp = [];

          // Trim BOMs from $row array keys
          foreach ($row as $key => $value) {
            unset($row[$key]);
            $newKey = json_encode($key);
            $newKey = str_replace('\ufeff', '', $newKey);
            $newKey = json_decode($newKey);
            $row[$newKey] = $value;
            if ($data[$newKey] != "") {
              $temp[$data[$newKey]] = json_decode(str_replace('\ufeff', '', json_encode($value)));
            }
          }
          array_push($new_arr, $temp);
      }

      // Validate new array
      $validator = Validation::createValidator();
      $constraint = new Assert\Collection([
        'givenName' => new Assert\NotBlank,
        'username' => new Assert\NotBlank,
        'surname' => new Assert\NotBlank,
      ]);

      foreach($new_arr as $single) {
        $violations = $validator->validate($single, $constraint);
        foreach ($violations as $violation) {
          if ($violation->getMessage()) {
            $request->getSession()->getFlashBag()->add('danger', 'Brak zmapowania któregoś z pól: "Username", "Surname", bądź "GivenName"');
            return $this->redirect('/');
          }
        }
      }

      // Creating form with mapped values and send to db
      foreach ($new_arr as $row) {
        $csv = new Csv();
        $index += 1;

        $em = $this->getDoctrine()->getManager();
        $fieldNames = $em->getClassMetadata('AppBundle\Entity\Csv')->getFieldNames();
        foreach ($row as $key => $value) {
            $csv->{'set'.$key}($value);
        }
        $form2 = $this->createForm(CsvType::class, $csv, ['data' => ['row' => $row]]);

        $new_data = [];
        foreach($row as $key => $value) {
          $new_data[$key] = $value;
        }
        $form2->submit($new_data);

        if ($form2->isSubmitted() && $form2->isValid()) {
          $em = $this->getDoctrine()->getManager();
          $em->persist($csv);
        }
      }
      $em->flush();

      // Generate report
      $time_end = microtime(true);
      $time = $time_end - $time_start;
      $time = round($time, 2);
      $incorrect = $counter;
      $correct = $index - $counter;

      return $this->render('default/summary.html.twig', ['time' => $time, 'correct' => $correct, 'incorrect' => $incorrect]);
    }

}
