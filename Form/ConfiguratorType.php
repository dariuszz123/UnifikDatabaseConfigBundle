<?php

namespace Unifik\DatabaseConfigBundle\Form;

use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BooleanNode;
use Symfony\Component\Config\Definition\EnumNode;
use Symfony\Component\Config\Definition\FloatNode;
use Symfony\Component\Config\Definition\IntegerNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\PrototypedArrayNode;
use Symfony\Component\Config\Definition\ScalarNode;
use Symfony\Component\Config\Definition\VariableNode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

use Unifik\DatabaseConfigBundle\Form\DataTransformer\ArrayEntityTransformer;

/**
 * This is only a PARTIAL and EXPERIMENTAL implementation of all the features available in the Symfony configuration
 * tree.
 *
 *  If you want a tree node to be handled by the configurator form, just set the "configurator" attribute to "true"
 *  in the tree builder on your bundle.
 *
 *  Example:
 *
 *      $rootNode
 *          ->children()
 *              ->integerNode('integer')
 *                  ->info('This is a configurable integer node')
 *                  ->defaultValue(21)
 *                  ->attribute('configurator', true)
 *              ->end()
 *          ->end();
 *
 *  Nodes type
 *   - BooleanNode           Supported.
 *   - IntegerNode           Supported.
 *   - FloatNode             Supported.
 *   - EnumNode              Supported.
 *   - ScalarNode            Supported.
 *   - ArrayNode             Supported.
 *   - PrototypedArrayNode   No support. This would required lots of work. Maybe in future version.
 *
 *  Validation rules support (http://symfony.com/doc/current/components/config/definition.html#validation-rules)
 *   - Not implemented at the moment.
 *
 *  Default values
 *   - Supported for all types. Displayed below the label of the field.
 *
 *  Information attribute
 *   - Supported for all types. Displayed below the label of the field.
 */
class ConfiguratorType extends AbstractType
{
    /**
     * Build Form
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new ArrayEntityTransformer());

        $this->processChildren($options['tree'], $builder);
    }

    /**
     * Takes a ArrayNode and build the form recursively
     *
     * @param ArrayNode            $arrayNode
     * @param FormBuilderInterface $builder
     */
    protected function processChildren(ArrayNode $arrayNode, FormBuilderInterface $builder)
    {
        foreach ($arrayNode->getChildren() as $node) {
            if (false == $node->getAttribute('configurator')) {
                // Nodes that are not explicitly configurable are skipped
                continue;
            } elseif ($node instanceof PrototypedArrayNode) {
                $this->nodeToField($node, $builder);
            } elseif ($node instanceof ArrayNode) {
                $builder->add($node->getName(), new ConfiguratorArrayType(), array('tree' => $node));
            } else {
                $this->nodeToField($node, $builder);
            }
        }
    }

    /**
     * Conversion of a node element to a form field.
     * The field is automatically added to the builder.
     *
     * @param NodeInterface        $node
     * @param FormBuilderInterface $builder
     */
    protected function nodeToField(NodeInterface $node, FormBuilderInterface $builder)
    {
        $options = array(
            'required' => $node->isRequired(),
            'constraints' => array(),
            'attr' => array()
        );

        if ($node instanceof EnumNode) {
            $options['choices'] = array_combine($node->getValues(), $node->getValues()); // generate identical key/value
            $options['placeholder'] = 'Default: ' . $node->getDefaultValue();
        } elseif ($node instanceof PrototypedArrayNode) {
            if ($choices = $node->getAttribute('choices', false)) {
                $options['expanded'] = true;
                $options['choices'] = array_combine($choices, $choices);
                $options['multiple'] = true;

                if ($node->getDefaultValue()) {
                    $options['data'] = $node->getDefaultValue();
                }
            } else {
                $options['type'] = $this->getFromType($node->getPrototype());
                $options['allow_add'] = true;
                $options['allow_delete'] = true;
                $options['attr']['class'] = 'collection';
                $options['data'] = $node->getDefaultValue();
            }
        }

        if ($node->isRequired()) {
            $options['constraints'][] = new NotBlank();
        }

        if (is_string($node->getDefaultValue()) || is_numeric($node->getDefaultValue())) {
            $options['attr']['placeholder'] = $node->getDefaultValue();
        }

        $builder->add($node->getName(), $this->getFromType($node), $options);
    }

    /**
     * Get field form type.
     *
     * @param NodeInterface $node
     *
     * @return null|string
     */
    private function getFromType(NodeInterface $node)
    {
        if ($node instanceof BooleanNode) {
            return 'checkbox';
        } elseif ($node instanceof IntegerNode) {
            return 'number';
        } elseif ($node instanceof FloatNode) {
            return 'number';
        } elseif ($node instanceof EnumNode) {
            return 'choice';
        } elseif ($node instanceof ScalarNode) {
            return 'text';
        } elseif ($node instanceof VariableNode) {
            return 'text';
        } elseif ($node instanceof PrototypedArrayNode && $node->hasAttribute('choices')) {
            return 'choice';
        } elseif ($node instanceof PrototypedArrayNode) {
            return 'collection';
        }

        return null;
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName()
    {
        return 'configurator';
    }

    /**
     * @inheritdoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'tree' => array()
        ));
    }
}
