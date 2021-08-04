<?php

declare(strict_types=1);

namespace Baraja\HtmlHeader;


use Nette\DI\CompilerExtension;

final class HtmlHeaderExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('htmlHeader'))
			->setFactory(HtmlHeader::class);
	}
}
