<?php

/*
 * This file is part of the W3rOneJsonSchemaBundle.
 *
 * (c) w3r-one <contact@w3r.one>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace W3rOne\JsonSchemaBundle\Transformer;

use W3rOne\JsonSchemaBundle\Utils;
use W3rOne\JsonSchemaBundle\Resolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractTransformer implements TransformerInterface
{
    protected RequestStack $requestStack;

    protected CsrfTokenManagerInterface $csrfTokenManager;

    protected TranslatorInterface $translator;

    protected Resolver $resolver;

    public function __construct(RequestStack $requestStack, CsrfTokenManagerInterface $csrfTokenManager, TranslatorInterface $translator, Resolver $resolver)
    {
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
        $this->resolver = $resolver;
    }

    public function transform(FormInterface $form): array
    {
        $schema = ['type' => 'string'];

        $this
            ->addTitle($form, $schema)
            ->addDescription($form, $schema)
            ->addDefault($form, $schema)
            ->addReadOnly($form, $schema)
            ->addWriteOnly($form, $schema)
            ->addOptions($form, $schema)
            ->addCustomOptions($form, $schema)
            ->addAttr($form, $schema);

        return $schema;
    }

    private function addTitle(FormInterface $form, array &$schema): self
    {
        if (null !== ($label = $form->getConfig()->getOption('label'))) {
            $schema['title'] = false === $label ? '' : $this->translate($form, $label, $form->getConfig()->getOption('label_translation_parameters', []));
        }
        else {
            $schema['title'] = null === $form->getParent() ? $form->getName() : '';
        }

        return $this;
    }

    private function addDescription(FormInterface $form, array &$schema): self
    {
        if (null !== ($help = $form->getConfig()->getOption('help'))) {
            $schema['description'] = $this->translate($form, $help, $form->getConfig()->getOption('help_translation_parameters', []));
        }

        return $this;
    }

    private function addDefault(FormInterface $form, array &$schema): self
    {
        if (null !== ($data = $form->getConfig()->getOption('data'))) {
            $schema['default'] = $data;
        }

        return $this;
    }

    private function addReadOnly(FormInterface $form, array &$schema): self
    {
        if (true === $form->getConfig()->getOption('disabled')) {
            $schema['readOnly'] = true;
        }

        return $this;
    }

    private function addWriteOnly(FormInterface $form, array &$schema): self
    {
        if (false === $form->getConfig()->getOption('mapped')) {
            $schema['writeOnly'] = true;
        }

        return $this;
    }

    private function addOptions(FormInterface $form, array &$schema): self
    {
        $widget = Utils::getWidget($form);

        $schema['options'] = array_merge($schema['options'] ?? [], [
            'widget' => !empty($widget) ? $widget : 'text',
            'layout' => $form->getConfig()->getOption('w3r_one_json_schema')['layout'] ?? $this->resolver->getDefaultLayout(),
        ]);

        return $this;
    }

    private function addCustomOptions(FormInterface $form, array &$schema): self
    {
        if (null !== ($customOptions = $form->getConfig()->getOption('w3r_one_json_schema'))) {
            $schema['options'] = array_merge($schema['options'] ?? [], $customOptions);
        }

        return $this;
    }

    protected function addAttr(FormInterface $form, array &$schema): self
    {
        if (!empty($attr = $form->getConfig()->getOption('attr'))) {
            if (!array_key_exists('options', $schema)) {
                $schema['options'] = [];
            }
            $schema['options']['attr'] = $attr;

            if (array_key_exists('title', $schema['options']['attr'])) {
                $schema['options']['attr']['title'] = $this->translate($form, $schema['options']['attr']['title'], $form->getConfig()->getOption('attr_translation_parameters', []));
            }
            if (array_key_exists('placeholder', $schema['options']['attr'])) {
                $schema['options']['attr']['placeholder'] = $this->translate($form, $schema['options']['attr']['placeholder'], $form->getConfig()->getOption('attr_translation_parameters', []));
            }
        }

        return $this;
    }

    protected function translate(FormInterface $form, ?string $translation, array $translationParameters = []): string
    {
        $globalFormLocale = $this->extractFormLocale($form);

        $translated = $this->translator->trans($translation ?? '', $translationParameters, $translationDomain = Utils::getTranslationDomain($form), $globalFormLocale);

        if (false !== $translation && null !== $translationDomain && $translation === $translated) {
            $translated = $this->translator->trans($translation ?? '', $translationParameters, Utils::getTranslationDomain($form, true), $globalFormLocale);
        }

        return is_string($translated) ? $translated : '';
    }

    protected function extractFormLocale(FormInterface $form): ?string
    {
        while (null !== ($parentForm = $form->getParent())) {
            $form = $parentForm;
        }

        return $form->getConfig()->getOption('attr')['lang'] ?? null;
    }
}
