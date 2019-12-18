<?php

namespace Drupal\wmsentry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;

class SettingsForm extends ConfigFormBase
{
    public function getFormId()
    {
        return 'wmsentry_settings';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('wmsentry.settings');

        $form['dsn'] = [
            '#type' => 'textfield',
            '#title' => 'DSN',
            '#description' => 'Data Source Name. A representation of the configuration required by the Sentry SDK.',
            '#default_value' => $config->get('dsn'),
        ];

        $form['release'] = [
            '#type' => 'textfield',
            '#title' => 'Release',
            '#description' => 'A string representing the version of your code that is deployed to an environment.',
            '#default_value' => $config->get('release'),
        ];

        $form['environment'] = [
            '#type' => 'textfield',
            '#title' => 'Environment',
            '#description' => 'A string representing the environment of this application (e.g. local, development, production)',
            '#default_value' => $config->get('environment'),
        ];

        $form['log_levels'] = [
            '#type' => 'checkboxes',
            '#title' => 'Log levels',
            '#description' => 'The RFC log levels that should be captured by Sentry',
            '#default_value' => $config->get('log_levels') ?? [],
            '#options' => $this->getLogLevelOptions(),
        ];

        $form['include_stacktrace_func_args'] = [
            '#type' => 'checkbox',
            '#title' => 'Include function arguments in stack trace',
            '#default_value' => $config->get('include_stacktrace_func_args'),
        ];

        $form['excluded_exceptions'] = [
            '#type' => 'textarea',
            '#title' => 'Excluded exceptions',
            '#description' => 'Sometimes you may want to skip capturing certain exceptions. This option sets the FQCN of the classes of the exceptions that you don’t want to capture. The check is done using the instanceof operator against each item of the array and if at least one of them passes the event will be discarded.',
            '#default_value' => $this->transformExcludedExceptions($config->get('excluded_exceptions')),
        ];

        $form['excluded_tags'] = [
            '#type' => 'textarea',
            '#title' => 'Excluded tags',
            '#description' => 'A list of tags that - if present on an event - will cause the captured exception to be skipped. Tags and their values should be seperated with a colon.',
            '#default_value' => $this->transformExcludedTags($config->get('excluded_tags')),
        ];

        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->config('wmsentry.settings')
            ->set('dsn', $form_state->getValue('dsn'))
            ->set('release', $form_state->getValue('release'))
            ->set('environment', $form_state->getValue('environment'))
            ->set('log_levels', $form_state->getValue('log_levels'))
            ->set('include_stacktrace_func_args', $form_state->getValue('include_stacktrace_func_args'))
            ->set('excluded_exceptions', $this->transformExcludedExceptions($form_state->getValue('excluded_exceptions')))
            ->set('excluded_tags', $this->transformExcludedTags($form_state->getValue('excluded_tags')))
            ->save();

        parent::submitForm($form, $form_state);
    }

    protected function getEditableConfigNames()
    {
        return ['wmsentry.settings'];
    }

    protected function getLogLevelOptions(): array
    {
        $options = [];

        foreach (RfcLogLevel::getLevels() as $level => $label) {
            $options[$level + 1] = $label;
        }

        return $options;
    }

    protected function transformExcludedExceptions($value)
    {
        if (is_string($value)) {
            return array_map('trim', explode(PHP_EOL, $value));
        }

        if (is_array($value)) {
            return implode(PHP_EOL, $value);
        }

        return null;
    }

    protected function transformExcludedTags($value)
    {
        if (is_string($value)) {
            $lines = array_map('trim', explode(PHP_EOL, $value));
            return array_map(function (string $line) {
                [$tag, $value] = array_map('trim', explode(':', $line));
                return compact('tag', 'value');
            }, $lines);
        }

        if (is_array($value)) {
            $lines = array_map(function (array $line) {
                return "{$line['tag']}: {$line['value']}";
            }, $value);
            return implode(PHP_EOL, $lines);
        }

        return null;
    }
}
