<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\widgets;

use Yii;
use yii\base\Arrayable;
use yii\base\Formatter;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Inflector;

/**
 * DetailView displays the detail of a single data [[model]].
 *
 * DetailView is best used for displaying a model in a regular format (e.g. each model attribute
 * is displayed as a row in a table.) The model can be either an instance of [[Model]]
 * or an associative array.
 *
 * DetailView uses the [[attributes]] property to determines which model attributes
 * should be displayed and how they should be formatted.
 *
 * A typical usage of DetailView is as follows:
 *
 * ~~~
 * echo DetailView::widget([
 *     'model' => $model,
 *     'attributes' => [
 *         'title',             // title attribute (in plain text)
 *         'description:html',  // description attribute in HTML
 *         [                    // the owner name of the model
 *             'label' => 'Owner',
 *             'value' => $model->owner->name,
 *         ],
 *     ],
 * ]);
 * ~~~
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DetailView extends Widget
{
	/**
	 * @var array|object the data model whose details are to be displayed. This can be either a [[Model]] instance
	 * or an associative array.
	 */
	public $model;
	/**
	 * @var array a list of attributes to be displayed in the detail view. Each array element
	 * represents the specification for displaying one particular attribute.
	 *
	 * An attribute can be specified as a string in the format of "Name" or "Name:Format", where "Name" refers to
	 * the attribute name, and "Format" represents the format of the attribute. The "Format" is passed to the [[Formatter::format()]]
	 * method to format an attribute value into a displayable text. Please refer to [[Formatter]] for the supported types.
	 *
	 * An attribute can also be specified in terms of an array with the following elements:
	 *
	 * - name: the attribute name. This is required if either "label" or "value" is not specified.
	 * - label: the label associated with the attribute. If this is not specified, it will be generated from the attribute name.
	 * - value: the value to be displayed. If this is not specified, it will be retrieved from [[model]] using the attribute name
	 *   by calling [[ArrayHelper::getValue()]]. Note that this value will be formatted into a displayable text
	 *   according to the "format" option.
	 * - format: the type of the value that determines how the value would be formatted into a displayable text.
	 *   Please refer to [[Formatter]] for supported types.
	 * - visible: whether the attribute is visible. If set to `false`, the attribute will NOT be displayed.
	 */
	public $attributes;
	/**
	 * @var string|callback the template used to render a single attribute. If a string, the token `{label}`
	 * and `{value}` will be replaced with the label and the value of the corresponding attribute.
	 * If a callback (e.g. an anonymous function), the signature must be as follows:
	 *
	 * ~~~
	 * function ($attribute, $index, $widget)
	 * ~~~
	 *
	 * where `$attribute` refer to the specification of the attribute being rendered, `$index` is the zero-based
	 * index of the attribute in the [[attributes]] array, and `$widget` refers to this widget instance.
	 */
	public $template = "<tr><th>{label}</th><td>{value}</td></tr>";
	/**
	 * @var array the HTML attributes for the container tag of this widget. The "tag" option specifies
	 * what container tag should be used. It defaults to "table" if not set.
	 */
	public $options = ['class' => 'table table-striped table-bordered detail-view'];
	/**
	 * @var array|Formatter the formatter used to format model attribute values into displayable texts.
	 * This can be either an instance of [[Formatter]] or an configuration array for creating the [[Formatter]]
	 * instance. If this property is not set, the "formatter" application component will be used.
	 */
	public $formatter;

	/**
	 * Initializes the detail view.
	 * This method will initialize required property values.
	 */
	public function init()
	{
		if ($this->model === null) {
			throw new InvalidConfigException('Please specify the "model" property.');
		}
		if ($this->formatter == null) {
			$this->formatter = Yii::$app->getFormatter();
		} elseif (is_array($this->formatter)) {
			$this->formatter = Yii::createObject($this->formatter);
		}
		if (!$this->formatter instanceof Formatter) {
			throw new InvalidConfigException('The "formatter" property must be either a Format object or a configuration array.');
		}
		$this->normalizeAttributes();
	}

	/**
	 * Renders the detail view.
	 * This is the main entry of the whole detail view rendering.
	 */
	public function run()
	{
		$rows = [];
		$i = 0;
		foreach ($this->attributes as $attribute) {
			$rows[] = $this->renderAttribute($attribute, $i++);
		}

		$tag = ArrayHelper::remove($this->options, 'tag', 'table');
		echo Html::tag($tag, implode("\n", $rows), $this->options);
	}

	/**
	 * Renders a single attribute.
	 * @param array $attribute the specification of the attribute to be rendered.
	 * @param integer $index the zero-based index of the attribute in the [[attributes]] array
	 * @return string the rendering result
	 */
	protected function renderAttribute($attribute, $index)
	{
		if (is_string($this->template)) {
			return strtr($this->template, [
				'{label}' => $attribute['label'],
				'{value}' => $this->formatter->format($attribute['value'], $attribute['format']),
			]);
		} else {
			return call_user_func($this->template, $attribute, $index, $this);
		}
	}

	/**
	 * Normalizes the attribute specifications.
	 * @throws InvalidConfigException
	 */
	protected function normalizeAttributes()
	{
		if ($this->attributes === null) {
			if ($this->model instanceof Model) {
				$this->attributes = $this->model->attributes();
			} elseif (is_object($this->model)) {
				$this->attributes = $this->model instanceof Arrayable ? $this->model->toArray() : array_keys(get_object_vars($this->model));
			} elseif (is_array($this->model)) {
				$this->attributes = array_keys($this->model);
			} else {
				throw new InvalidConfigException('The "model" property must be either an array or an object.');
			}
			sort($this->attributes);
		}

		foreach ($this->attributes as $i => $attribute) {
			if (is_string($attribute)) {
				if (!preg_match('/^(\w+)(\s*:\s*(\w+))?$/', $attribute, $matches)) {
					throw new InvalidConfigException('The attribute must be specified in the format of "Name" or "Name:Format"');
				}
				$attribute = [
					'name' => $matches[1],
					'format' => isset($matches[3]) ? $matches[3] : 'text',
				];
			}

			if (!is_array($attribute)) {
				throw new InvalidConfigException('The attribute configuration must be an array.');
			}

			if (isset($attribute['visible']) && !$attribute['visible']) {
				unset($this->attributes[$i]);
				continue;
			}

			if (!isset($attribute['format'])) {
				$attribute['format'] = 'text';
			}
			if (isset($attribute['name'])) {
				$name = $attribute['name'];
				if (!isset($attribute['label'])) {
					$attribute['label'] = $this->model instanceof Model ? $this->model->getAttributeLabel($name) : Inflector::camel2words($name, true);
				}
				if (!array_key_exists('value', $attribute)) {
					$attribute['value'] = ArrayHelper::getValue($this->model, $name);
				}
			} elseif (!isset($attribute['label']) || !array_key_exists('value', $attribute)) {
				throw new InvalidConfigException('The attribute configuration requires the "name" element to determine the value and display label.');
			}

			$this->attributes[$i] = $attribute;
		}
	}
}
