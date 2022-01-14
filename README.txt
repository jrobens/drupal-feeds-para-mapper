
Copyright 2018 Hussein El-hussein


1. Call to getFieldDefinition on field object of the wrong type for this method

    Error: Call to undefined method Drupal\feeds\TargetDefinition::getFieldDefinition() in Drupal\feeds_para_mapper\Importer->resetTypes() (line 122 of /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Importer.php) #0 /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Importer.php(100):

Which is https://www.drupal.org/project/feeds_para_mapper/issues/3244189

2. Error: Call to a member function getColumns() on bool in

Drupal\Core\Entity\Query\Sql\Tables->addField() (line 246 of

3. Targets array problem


    Warning: in_array() expects parameter 2 to be array, null given in Drupal\feeds_para_mapper\Mapper->getTargets() (line 64 of /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Mapper.php)

    0 /Users/jrobens/Sites/dms-climatebonds/web/core/includes/bootstrap.inc(346): _drupal_error_handler_real(2, 'in_array() expe...', '/Users/jrobens/...', 64)
      1 [internal function]: _drupal_error_handler(2, 'in_array() expe...', '/Users/jrobens/...', 64, Array)
    2 /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Mapper.php(64): in_array('entity_referenc...', NULL)
    3 /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Feeds/Target/WrapperTarget.php(92): Drupal\feeds_para_mapper\Mapper->getTargets('node', 'bond')
    4 /Users/jrobens/Sites/dms-climatebonds/web/modules/contrib/feeds/src/Entity/FeedType.php(305):


4. Field field_para_issuer_information is unknown.

See cbi_cbid.module - the bundle is 'bond' even though the entity type is feed.

5. call to a member function getColumns (again)

/Users/jrobens/Sites/dms-climatebonds/web/core/lib/Drupal/Core/Entity/Query/Sql/Tables.php #246

    Error: Call to a member function getColumns() on bool in Drupal\Core\Entity\Query\Sql\Tables->addField() (line 246 of
    /Users/jrobens/Sites/dms-climatebonds/web/core/lib/Drupal/Core/Entity/Query/Sql/Tables.php) #0 /Users/jrobens/Sites/dms-climatebonds/web/core/lib/Drupal/Core/Entity/Query/Sql/Condition.php(51):
    Drupal\Core\Entity\Query\Sql\Tables->addField('paragraph_ident...', 'INNER', NULL)


* The Feeds target "field_para_issuer_information.entity:paragraph.field_bond_country" does not exist = taxonomy
* The Feeds target "field_para_issuer_information.entity:paragraph.field_bond_issuer" does not exist = reference entity
* The Feeds target "field_para_deal_information.entity:paragraph.field_bond_instrument_type" does not exist. = taxonomy
* The Feeds target "field_para_deal_information.entity:paragraph.field_bond_label" does not exist = taxonomy
* The Feeds target "field_para_deal_information.entity:paragraph.field_bond_exchanges" does not exist. = multiselect taxonomy
* The Feeds target "field_para_green_eligibility.entity:paragraph.field_bond_cert_criteria" does not exist = entity reference taxonomy


6. Looking for 'in common'

How best to test for empty paragraphs? Is this looking for paragraphs with no fields?

    1800086   22/Dec 19:44   php     Notice     Notice: Trying to access array offset on value of type null in Drupal\feeds_para_mapper\Importer->checkValuesChanges() (line 733 of
     /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Importer.php) #0 /Users/jrobens/Sites/dms-climatebonds/web/core/includes/bootstrap.inc(346):
    _drupal_error_handler_real(8, 'Trying to acces...', '/Users/jrobens/...', 733)
    1 /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Importer.php(733): _drupal_error_handler(8, 'Trying to acces...', '/Users/jrobens/...', 733, Array)

7. Mapper


    1804161   22/Dec 20:22   php    Warning    Warning: in_array() expects parameter 2 to be array, null given in Drupal\feeds_para_mapper\Mapper->getTargets() (line 66 of
     /Users/jrobens/Sites/dms-climatebonds/web/modules/patched/feeds_para_mapper/src/Mapper.php) #0 /Users/jrobens/Sites/dms-climatebonds/web/core/includes/bootstrap.inc(346): _drupal_error_handler_real(2,
    'in_array() expe...', '/Users/jrobens/...', 66)
    #1 [internal function]: _drupal_error_handler(2, 'in_array() expe...', '/Users/jrobens/...', 66, Array)


8. taxonomy mapping

   https://www.drupal.org/project/feeds_para_mapper/issues/3255360

Use tamper to split the commas

9. Role plugin

Type	feeds_para_mapper
Date	Tuesday, January 11, 2022 - 19:23
User	jrobens
Location	https://dms.climatebonds.net/admin/structure/feeds/manage/bond/mapping
Referrer	https://dms.climatebonds.net/admin/structure/feeds/manage/bond/mapping
Message	Plugin user_role does not have a field_types key
Severity	Warning
Hostname	159.196.169.95

INTRODUCTION
-----------
This module allows mapping to Paragraphs fields.

FEATURES:
---------
* Supports mapping to nested Paragraphs fields.
* Supports mapping to multi-valued Paragraphs fields.
* Supports updating Paragraphs fields values.
Note: As long as the field you are trying to map to supports feeds,
then this module supports it, for example,
Interval field does not, it needs patching, see:
https://www.drupal.org/project/interval/issues/2032715

REQUIREMENTS
-------------
This module requires the following modules:

* Feeds (https://drupal.org/project/feeds)
* Paragraphs (https://drupal.org/project/paragraphs)

INSTALLATION
------------
To install, copy the feeds_para_mapper directory,
and all its contents to your modules directory.

CONFIGURATION
-------------
It has no configuration page.
To enable this module:
visit administer -> modules, and enable Paragraphs Mapper.

USAGE
-------------
For mapping multiple values, use Feeds Tamper:
https://www.drupal.org/project/feeds_tamper
And follow this guide:
https://www.drupal.org/node/2287473

Author
------
Hussein El-hussein (function.op@gmail.com)
