<?php

namespace Simp\Core\lib\forms;

use Simp\Core\modules\cron\Cron;
use Simp\Default\FieldSetField;
use Simp\Default\SelectField;
use Simp\Default\TextAreaField;
use Simp\FormBuilder\FormBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AddCronForm extends FormBase {

    public function getFormId(): string
    {
        return "cron_form";
    }

    public function buildForm(array &$form): array
    {
        $form['title'] = [
            'type'=> 'text',
            'name' => 'title',
            'id' => 'title',
            'class'=> [],
            'label' => 'Cron title'
        ];

        $subscribers = Cron::factory()->getSubscribers();
        $form['subscribers'] = [
            'type' => 'select',
            'name' => 'subscribers',
            'id' => 'subscribers',
            'class' => [],
            'label' => 'Subscribers',
            'option_values' => $subscribers,
            'handler'=> SelectField::class,
        ];

        $options = [
    'every|minute' => 'Every minute',
    'every|hour'   => 'Every hour',
    'every|day'    => 'Every day',
    'every|week'   => 'Every week',
    'every|month'  => 'Every month',
    'every|year'   => 'Every year',
    ];

       $form['timing_wrapper'] = [
        'type' => 'fieldset',
        'name' => 'timing_wrapper',
        'label' => 'Timing Settings',
        'id'=> 'timing_wrapper',
        'class' => [],
        'inner_field' => [
            'every_timing' => [
                  'type' => 'select',
                  'name' => 'every_timing',
                  'id' => 'timing',
                  'class' => [],
                  'label' => 'Every Timing',
                  'option_values' => $options,
                  'handler'=> SelectField::class,
                  'description'=> 'select option here means the cron will run of every bases'
            ],
            'once_timing' => [
                'type' => 'date',
                'name' => 'once_timing',
                'label' => 'Once Date',
                'id' => 'once_timing',
                'class' => [],
                'description' => 'give value to this field if this cron is to run once on specific date'
            ],
            'ontime_timing_wrapper' => [
                'type' => 'fieldset',
                'name' => 'ontime_timing_wrapper',
                'label' => 'Ontime Settings',
                'id' => 'ontime_timing_wrapper',
                'class' => [],
                'handler' => FieldSetField::class,
                'inner_field' => [
                    'ontime_every_timing' => [
                        'type' => 'select',
                        'name' => 'timing',
                        'id' => 'timing',
                        'class' => [],
                        'label' => 'Frequency',
                        'option_values' => [
                            '10 minute' => 'Every 10 minute',
                            '20 minute' => 'Every 20 minute',
                            '30 minute' => 'Every 30 minute',
                            '40 minute' => 'Every 40 minute',
                            '50 minute' => 'Every 50 minute',
                            '1 hour' => 'Every 1 hour',
                            '2 days' => 'Every 2 days',
                            '3 days' => 'Every 3 days',
                            '4 days' => 'Every 4 days',
                            '5 days' => 'Every 5 days',
                            '6 days' => 'Every 6 days',
                            '7 days' => 'Every 7 days',
                            '8 days' => 'Every 8 days',
                            '9 days' => 'Every 9 days',
                            '10 days' => 'Every 10 days',
                            '11 days' => 'Every 11 days',
                            '12 days' => 'Every 12 days',
                            '13 days' => 'Every 13 days',
                            '14 days' => 'Every 14 days',
                            '15 days' => 'Every 15 days',
                            '16 days' => 'Every 16 days',
                            '17 days' => 'Every 17 days',
                            '18 days' => 'Every 18 days',
                            '19 days' => 'Every 19 days',
                            '20 days' => 'Every 20 days',
                            '21 days' => 'Every 21 days',
                            '22 days' => 'Every 22 days',
                            '23 days' => 'Every 23 days',
                            '24 days' => 'Every 24 days',
                            '25 days' => 'Every 25 days',
                            '26 days' => 'Every 26 days',
                            '27 days' => 'Every 27 days',
                            '28 days' => 'Every 28 days',
                            '29 days' => 'Every 29 days',
                            '30 days' => 'Every 30 days',
                            '31 days' => 'Every 31 days',
                        ],
                        'handler'=> SelectField::class
                    ],
                    'ontime_timing' => [
                        'type' => 'datetime-local',
                        'name' => 'ontime_timing',
                        'label' => 'Start Date & Time',
                        'id'=> 'ontime_timing',
                        'class' => [],
                    ]
                ],
            ],
        ],
        'handler' => FieldSetField::class
       ];

        $form['description'] = [
            'type' => 'textarea',
            'name' => 'description',
            'label' => 'Description',
            'id' => 'description',
            'class' => [],
            'handler' => TextAreaField::class
        ];

        $form['submit'] = [
            'type' => 'submit',
            'name' => 'submit',
            'label' => '',
            'default_value' => 'Submit',
            'id'=> 'submit'
        ];
        return $form;
    }

    public function validateForm(array $form): void
    {

    }

    public function submitForm(array &$form): void
    {
        $data = \array_map(function($item){ return $item->getValue(); },$form);
        $name = \str_replace(' ', '_', $data['title']);
        $name = \strtolower($name);
        $cron[$name]['title'] = $data['title'];
        $cron[$name]['description'] = $data['description'];

        if (!empty($data['timing_wrapper']['every_timing']) && \strlen($data['timing_wrapper']['every_timing']) > 5) {
            $cron[$name]['timing'] = $data['timing_wrapper']['every_timing'];
        }
        elseif (!empty($data['timing_wrapper']['once_timing']) && \strlen($data['timing_wrapper']['once_timing']) > 5) {
            $cron[$name]['timing'] = 'once|'.$data['timing_wrapper']['once_timing'];
        }
        elseif(!empty($data['timing_wrapper']['ontime_timing_wrapper']['ontime_every_timing']) && \strlen($data['timing_wrapper']['ontime_timing_wrapper']['ontime_every_timing']) > 5) {
            $cron[$name]['timing'] = 'ontime|'.$data['timing_wrapper']['ontime_timing_wrapper']['ontime_timing']
            . "@". $data['timing_wrapper']['ontime_timing_wrapper']['ontime_every_timing'];
        }
        $cron[$name]['subscribers'] = $data['subscribers'];
        $array = \array_values($cron);

        Cron::factory()->add($name, reset($array));
        $redirect = new RedirectResponse('/cron/manage');
        $redirect->send();
    }



}
