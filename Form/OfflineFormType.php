<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class OfflineFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text', array('required' => true, 'label' => 'sync_name'));
        $builder->add('password', 'password', array('required' => true, 'label' => 'sync_pwd'));
        $builder->add('url', 'url', array('required' => true, 'label' => 'sync_url', 'attr' => array('placeholder' => 'url_ex')));
    }

    public function getName()
    {
        return 'offline_form';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
        ->setDefaults(
            array(
                'translation_domain' => 'offline',
                'no_captcha' => true
                )
        );
    }

}
