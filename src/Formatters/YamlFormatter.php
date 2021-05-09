<?php namespace Mezatsong\SwaggerDocs\Formatters;

use Mezatsong\SwaggerDocs\Exceptions\ExtensionNotLoaded;

/**
 * Class YamlFormatter
 * @package Mezatsong\SwaggerDocs\Formatters
 */
class YamlFormatter extends AbstractFormatter {

    /**
     * @inheritDoc
     * @return string
     * @throws ExtensionNotLoaded
     */
    public function format(): string {
        if (!extension_loaded('yaml')) {
            throw new ExtensionNotLoaded('YAML extends must be loaded to use the `yaml` output format');
        }
        return yaml_emit($this->documentation);
    }

}
