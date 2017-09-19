<?php
namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MapFieldsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $header = $options['data']['header'];
        $fieldNames = $options['data']['fieldNames'];

        foreach ($header as $key => $value) {
          $newValue = trim(json_encode($value));
          $newValue = str_replace('\ufeff', '', $newValue);
          $newValue = json_decode($newValue);
          unset($header[$value]);

          $builder->add($newValue, ChoiceType::class, ['label' => $newValue, 'choices' => $fieldNames, 'placeholder' => '-- Wybierz opcję --', 'required' => false, 'attr' => ['class' => 'form-control']]);
        }
        $builder->add('Import', SubmitType::class, ['attr' => ['class' => 'btn btn-primary']]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'csrf_protection' => false,
        ));
    }
}
