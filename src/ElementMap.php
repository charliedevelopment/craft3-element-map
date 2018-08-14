<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap;

use Craft;
use craft\base\Plugin;

// NOTE: Added the following to support custom Table Attributes
use craft\base\Element;
use craft\elements\Entry;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use yii\base\Event;

/**
 * The main Craft plugin class.
 */
class ElementMap extends Plugin
{

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		parent::init();

		$this->setComponents([
			'renderer' => \charliedev\elementmap\services\Renderer::class,
		]);

		Craft::$app->getView()->hook('cp.entries.edit.details', [$this, 'renderEntryElementMap']);
		Craft::$app->getView()->hook('cp.categories.edit.details', [$this, 'renderCategoryElementMap']);
		Craft::$app->getView()->hook('cp.users.edit.details', [$this, 'renderUserElementMap']);
		Craft::$app->getView()->hook('cp.commerce.product.edit.details', [$this, 'renderProductElementMap']);

    // NOTE: Added the following events to support custom Table Attributes
    Event::on(Entry::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function(RegisterElementTableAttributesEvent $event) {
      $event->tableAttributes['elementMap'] = ['label' => 'In Use On'];
    });

    Event::on(Entry::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, function(SetElementTableAttributeHtmlEvent $event) {
      if ($event->attribute === 'elementMap') {
        /** @var Entry $entry */
        $entry = $event->sender;

        $event->html = $this->renderer->render($entry->id, $entry->site->id, true);

        // Prevent other event listeners from getting invoked
        $event->handled = true;
      }
    });
	}

	/**
	 * Renders the element map for an entry within the entry editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderEntryElementMap(array &$context)
	{
		if ($context['entry']['id'] != null) {
			return $this->renderer->render($context['entry']['id'], $context['site']['id']);
		}
	}

	/**
	 * Renders the element map for a category within the category editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderCategoryElementMap(array &$context)
	{
		if ($context['category']['id'] != null) {
			return $this->renderer->render($context['category']['id'], $context['site']['id']);
		}
	}

	/**
	 * Renders the element map for a user within the user editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderUserElementMap(array &$context)
	{
		if ($context['user']['id'] != null) {
			return $this->renderer->render($context['user']['id'], Craft::$app->getSites()->getPrimarySite()->id);
		}
	}

	/**
	 * Renders the element map for a product within the product editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderProductElementMap(array &$context)
	{
		if ($context['product']['id'] != null) {
			return $this->renderer->render($context['product']['id'], $context['site']['id']);
		}
	}
}
