<?php
namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CsvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $row = $options['data']['row'];
        foreach($row as $key => $value) {
          $builder->add($key, TextType::class, ['empty_data' => $value, 'required' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'csrf_protection' => false,
        ));
    }
}
