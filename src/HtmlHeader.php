<?php

declare(strict_types=1);

namespace Baraja\HtmlHeader;


/**
 * Renders the complete valid header HTML content based on MetaBuilder.
 * Simply define individual tags by calling methods and then get the entire contents of the header:
 *
 * $header = new HtmlHeader;
 * $header->title('My webpage');
 * $header->meta('description', 'Awesome page...');
 * // or shortcut:
 * $header->metaDescription('Awesome page...');
 *
 * $header->link('canonical', 'https://baraja.cz');
 * $header->link('alternate', [
 *     'hreflang' => 'cs',
 *     'href' => 'https://baraja.cz',
 * ]);
 *
 * $header->jsonld([
 *     '@context' => 'http://schema.org',
 *     '@type' => 'Person',
 *     'name' => 'Jan Barášek',
 * ]);
 */
final class HtmlHeader
{
	/** @var string[] */
	private array $order = ['title', 'meta', 'og', 'twitter', 'link', 'json-ld'];

	/** @var string[][]|string[][][] */
	private array $tags = [];


	public function __toString(): string
	{
		return $this->render();
	}


	/**
	 * Render all or a specific group of HTML meta tags.
	 *
	 * @param string[] $groups render specific groups or keep empty for all records.
	 */
	public function render(?array $groups = null): string
	{
		$items = [];
		foreach (($groups ?? $this->order) as $group) {
			$items[] = $this->renderGroup($group);
		}

		return trim(implode('', $items));
	}


	/**
	 * @param string[] $order
	 */
	public function setCustomOrderingStrategy(array $order): void
	{
		$this->order = $order;
	}


	/**
	 * Build an HTML link tag.
	 *
	 * @param string|string[]|null $value
	 */
	public function link(string $key, $value): void
	{
		if (!empty($value)) {
			$attributes = ['rel' => $key];
			if (is_array($value)) {
				foreach ($value as $valueKey => $v) {
					$attributes[$valueKey] = $v;
				}
			} else {
				$attributes['href'] = $value;
			}

			$this->addToTagsGroup('link', $key, $this->createTag('link', $attributes));
		}
	}


	public function metaDescription(string $content): void
	{
		$this->meta('description', $content);
	}


	/**
	 * Build an HTML meta tag.
	 *
	 * @param string|string[]|null $value
	 */
	public function meta(string $key, $value): void
	{
		if (!empty($value)) {
			$attributes = ['name' => $key];
			if (is_array($value)) {
				foreach ($value as $valueKey => $v) {
					$attributes[$valueKey] = $v;
				}
			} else {
				$attributes['content'] = $value;
			}

			$this->addToTagsGroup('meta', $key, $this->createTag('meta', $attributes));
		}
	}


	/** Build an Open Graph meta tag. */
	public function og(string $key, string $value, bool $prefixed = true): void
	{
		if (!empty($value)) {
			$key = $prefixed ? 'og:' . $key : $key;
			$this->addToTagsGroup('og', $key, $this->createTag('meta', [
				'property' => $key,
				'content' => $value,
			]));
		}
	}


	/**
	 * Build an JSON linked data meta tag.
	 *
	 * @param mixed[] $schema
	 */
	public function jsonld(array $schema): void
	{
		if ($schema === []) {
			return;
		}

		try {
			$json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
		} catch (\Throwable $e) {
			throw new \InvalidArgumentException('Invalid json: ' . $e->getMessage(), $e->getCode(), $e);
		}
		$this->tags['json-ld'][] = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
	}


	/** Build a Title HTML tag. */
	public function title(?string $value): void
	{
		if ($value === null) {
			return;
		}
		if (($value = trim($value)) !== '') {
			$this->tags['title'][] = '<title>' . htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
		}
	}


	/** Build a Twitter Card meta tag. */
	public function twitter(string $key, string $value, bool $prefixed = true): void
	{
		if (!empty($value)) {
			$key = $prefixed ? 'twitter:' . $key : $key;
			$this->addToTagsGroup('twitter', $key, $this->createTag('meta', [
				'name' => $key,
				'content' => $value,
			]));
		}
	}


	/** Render all HTML meta tags from a specific group. */
	private function renderGroup(string $group): string
	{
		if (!isset($this->tags[$group])) {
			return '';
		}

		$html = [];
		foreach ($this->tags[$group] as $tag) {
			if (is_array($tag)) {
				foreach ($tag as $t) {
					$html[] = $t;
				}
			} else {
				$html[] = $tag;
			}
		}

		return count($html) > 0
			? implode("\n", $html) . "\n"
			: '';
	}


	/** Add single HTML element to tags group. */
	private function addToTagsGroup(string $group, string $key, string $tag): void
	{
		if (isset($this->tags[$group][$key])) {
			if (is_array($this->tags[$group][$key])) {
				$this->tags[$group][$key][] = $tag;
			} else {
				$this->tags[$group][$key] = [$this->tags[$group][$key], $tag];
			}
		} else {
			$this->tags[$group][$key] = $tag;
		}
	}


	/**
	 * Build an HTML tag
	 *
	 * @param string[] $attributes
	 */
	private function createTag(string $tagName, array $attributes = []): string
	{
		$escapeAttr = static function (string $s): string {
			if (strpos($s, '`') !== false && strpbrk($s, ' <>"\'') === false) {
				$s .= ' '; // protection against innerHTML mXSS vulnerability nette/nette#1496
			}

			return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', true);
		};

		$attrItems = [];
		foreach ($attributes as $key => $value) {
			if ($value !== null) {
				$attrItems[] = $escapeAttr((string) $key) . '="' . $escapeAttr($value) . '"';
			}
		}

		return '<' . $tagName . (count($attrItems) > 0 ? ' ' . implode(' ', $attrItems) : '') . '>';
	}
}
