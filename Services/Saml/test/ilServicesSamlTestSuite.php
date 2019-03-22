<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'libs/composer/vendor/autoload.php';

use PHPUnit\Framework\TestSuite;

/**
 * Class ilServicesSamlTestSuite
 */
class ilServicesSamlTestSuite extends TestSuite
{
	/**
	 * @return self
	 */
	public static function suite()
	{
		$suite = new self();

		require_once 'Services/Saml/test/ilSamlMappedUserAttributeValueParserTest.php';
		$suite->addTestSuite(ilSamlMappedUserAttributeValueParserTest::class);

		return $suite;
	}
}