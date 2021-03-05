<?php

namespace Drupal\wmsentry\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\State\State;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SettingsForm extends ConfigFormBase
{
    /** @var State */
    protected $state;

    public static function create(ContainerInterface $container)
    {
        $instance = parent::create($container);
        $instance->state = $container->get('state');

        return $instance;
    }

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

        if ($release = $this->state->get('wmsentry.release')) {
            $destination = Url::fromRoute('<current>')->toString();
            $setUrl = Url::fromRoute('wmsentry.set_release')->toString();
            $unsetUrl = Url::fromRoute('wmsentry.unset_release', ['destination' => $destination])->toString();

            $form['release']['#disabled'] = true;
            $form['release']['#description'] .= sprintf(' <br><b>This value is overridden by the release set 
                using the <code>%s</code> endpoint. <a href="%s">Remove the override</a>.</b>', $setUrl, $unsetUrl);
        }

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
            '#default_value' => $this->transformStringList($config->get('excluded_exceptions')),
        ];

        $form['excluded_tags'] = [
            '#type' => 'textarea',
            '#title' => 'Excluded tags',
            '#description' => 'A list of tags that - if present on an event - will cause the captured exception to be skipped. Tags and their values should be seperated with a colon.',
            '#default_value' => $this->transformExcludedTags($config->get('excluded_tags')),
        ];

        $form['in_app_include'] = [
            '#type' => 'textarea',
            '#title' => 'Included file paths',
            '#description' => 'A list of path prefixes that belong to the app. Paths are relative to the Drupal root. This option takes precedence over in_app_exclude.',
            '#default_value' => $this->transformStringList($config->get('in_app_include')),
        ];

        $form['in_app_exclude'] = [
            '#type' => 'textarea',
            '#title' => 'Excluded file paths',
            '#description' => 'A list of path prefixes that do not belong to the app, but rather to third-party packages. Paths are relative to the Drupal root. Modules considered not part of the app will be hidden from stack traces by default. This option can be overridden using in_app_include.',
            '#default_value' => $this->transformStringList($config->get('in_app_exclude')),
        ];

        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->config('wmsentry.settings')
            ->set('dsn', $form_state->getValue('dsn'))
            ->set('release', $form_state->getValue('release'))
            ->set('environment', $form_state->getValue('environment'))
            ->set('log_levels', $form_state->getValue('log_levels'))
            ->set('include_stacktrace_func_args', $form_state->getValue('include_stacktrace_func_args'))
            ->set('excluded_exceptions', $this->transformStringList($form_state->getValue('excluded_exceptions')))
            ->set('excluded_tags', $this->transformExcludedTags($form_state->getValue('excluded_tags')))
            ->set('in_app_include', $this->transformStringList($form_state->getValue('in_app_include')))
            ->set('in_app_exclude', $this->transformStringList($form_state->getValue('in_app_exclude')))
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

    protected function transformStringList($value)
    {
        if (is_string($value)) {
            $values = explode(PHP_EOL, $value);
            $values = array_map('trim', $values);

            return array_filter($values);
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
            return array_map(function (string $line): array {
                [$tag, $value] = array_map('trim', explode(':', $line));
                return compact('tag', 'value');
            }, $lines);
        }

        if (is_array($value)) {
            $lines = array_map(function (array $line): string {
                return "{$line['tag']}: {$line['value']}";
            }, $value);
            return implode(PHP_EOL, $lines);
        }

        return null;
    }
}
