<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Forms\Config\UserBackend;

use Exception;
use Icinga\Data\ResourceFactory;
use Icinga\Protocol\Ldap\LdapCapabilities;
use Icinga\Protocol\Ldap\LdapConnection;
use Icinga\Protocol\Ldap\LdapException;
use Icinga\Web\Form;

/**
 * Form class for adding/modifying LDAP user backends
 */
class LdapBackendForm extends Form
{
    /**
     * The ldap resource names the user can choose from
     *
     * @var array
     */
    protected $resources;

    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_authbackend_ldap');
    }

    /**
     * Set the resource names the user can choose from
     *
     * @param   array   $resources      The resources to choose from
     *
     * @return  $this
     */
    public function setResources(array $resources)
    {
        $this->resources = $resources;
        return $this;
    }

    /**
     * Create and add elements to this form
     *
     * @param   array   $formData
     */
    public function createElements(array $formData)
    {
        $isAd = isset($formData['type']) ? $formData['type'] === 'msldap' : false;

        $this->addElement(
            'text',
            'name',
            array(
                'required'      => true,
                'label'         => $this->translate('Backend Name'),
                'description'   => $this->translate(
                    'The name of this authentication provider that is used to differentiate it from others.'
                )
            )
        );
        $this->addElement(
            'select',
            'resource',
            array(
                'required'      => true,
                'label'         => $this->translate('LDAP Connection'),
                'description'   => $this->translate(
                    'The LDAP connection to use for authenticating with this provider.'
                ),
                'multiOptions'  => !empty($this->resources)
                    ? array_combine($this->resources, $this->resources)
                    : array()
            )
        );

        $baseDn = null;
        $hasAdOid = false;
        if (! $isAd && !empty($this->resources)) {
            $this->addElement(
                'button',
                'discovery_btn',
                array(
                    'class'             => 'control-button',
                    'type'              => 'submit',
                    'value'             => 'discovery_btn',
                    'label'             => $this->translate('Discover', 'A button to discover LDAP capabilities'),
                    'title'             => $this->translate(
                        'Push to fill in the chosen connection\'s default settings.'
                    ),
                    'decorators'        => array(
                        array('ViewHelper', array('separator' => '')),
                        array('Spinner'),
                        array('HtmlTag', array('tag' => 'div', 'class' => 'control-group form-controls'))
                    ),
                    'formnovalidate'    => 'formnovalidate'
                )
            );

            if ($this->getElement('discovery_btn')->isChecked()) {
                $connection = ResourceFactory::create(
                    isset($formData['resource']) ? $formData['resource'] : reset($this->resources)
                );

                try {
                    $capabilities = $connection->bind()->getCapabilities();
                    $baseDn = $capabilities->getDefaultNamingContext();
                    $hasAdOid = $capabilities->isActiveDirectory();
                } catch (Exception $e) {
                    $this->warning(sprintf(
                        $this->translate('Failed to discover the chosen LDAP connection: %s'),
                        $e->getMessage()
                    ));
                }
            }
        }

        if ($isAd || $hasAdOid) {
            // ActiveDirectory defaults
            $userClass = 'user';
            $filter = '!(objectClass=computer)';
            $userNameAttribute = 'sAMAccountName';
        } else {
            // OpenLDAP defaults
            $userClass = 'inetOrgPerson';
            $filter = null;
            $userNameAttribute = 'uid';
        }

        $this->addElement(
            'text',
            'user_class',
            array(
                'preserveDefault'   => true,
                'required'          => ! $isAd,
                'ignore'            => $isAd,
                'disabled'          => $isAd ?: null,
                'label'             => $this->translate('LDAP User Object Class'),
                'description'       => $this->translate('The object class used for storing users on the LDAP server.'),
                'value'             => $userClass
            )
        );
        $this->addElement(
            'text',
            'filter',
            array(
                'preserveDefault'   => true,
                'allowEmpty'        => true,
                'value'             => $filter,
                'label'             => $this->translate('LDAP Filter'),
                'description'       => $this->translate(
                    'An additional filter to use when looking up users using the specified connection. '
                    . 'Leave empty to not to use any additional filter rules.'
                ),
                'requirement'       => $this->translate(
                    'The filter needs to be expressed as standard LDAP expression.'
                    . ' (e.g. &(foo=bar)(bar=foo) or foo=bar)'
                ),
                'validators'        => array(
                    array(
                        'Callback',
                        false,
                        array(
                            'callback'  => function ($v) {
                                // This is not meant to be a full syntax check. It will just
                                // ensure that we can safely strip unnecessary parentheses.
                                $v = trim($v);
                                return ! $v || $v[0] !== '(' || (
                                    strpos($v, ')(') !== false ? substr($v, -2) === '))' : substr($v, -1) === ')'
                                );
                            },
                            'messages'  => array(
                                'callbackValue' => $this->translate('The filter is invalid. Please check your syntax.')
                            )
                        )
                    )
                )
            )
        );
        $this->addElement(
            'text',
            'user_name_attribute',
            array(
                'preserveDefault'   => true,
                'required'          => ! $isAd,
                'ignore'            => $isAd,
                'disabled'          => $isAd ?: null,
                'label'             => $this->translate('LDAP User Name Attribute'),
                'description'       => $this->translate(
                    'The attribute name used for storing the user name on the LDAP server.'
                ),
                'value'             => $userNameAttribute
            )
        );
        $this->addElement(
            'hidden',
            'backend',
            array(
                'disabled'  => true,
                'value'     => $isAd ? 'msldap' : 'ldap'
            )
        );
        $this->addElement(
            'text',
            'base_dn',
            array(
                'preserveDefault'   => true,
                'required'          => false,
                'label'             => $this->translate('LDAP Base DN'),
                'description'       => $this->translate(
                    'The path where users can be found on the LDAP server. Leave ' .
                    'empty to select all users available using the specified connection.'
                ),
                'value'             => $baseDn
            )
        );

        $this->addElement(
            'text',
            'domain',
            array(
                'label'         => $this->translate('Domain'),
                'description'   => $this->translate(
                    'The domain the LDAP server is responsible for upon authentication.'
                    . ' Note that if you specify a domain here,'
                    . ' the LDAP backend only authenticates users who specify a domain upon login.'
                    . ' If the domain of the user matches the domain configured here, this backend is responsible for'
                    . ' authenticating the user based on the username without the domain part.'
                    . ' If your LDAP backend holds usernames with a domain part or if it is not necessary in your setup'
                    . ' to authenticate users based on their domains, leave this field empty.'
                ),
                'preserveDefault' => true
            )
        );

        $this->addElement(
            'button',
            'btn_discover_domain',
            array(
                'class'             => 'control-button',
                'type'              => 'submit',
                'value'             => 'discovery_btn',
                'label'             => $this->translate('Discover the domain'),
                'title'             => $this->translate(
                    'Push to disover and fill in the domain of the LDAP server.'
                ),
                'decorators'        => array(
                    array('ViewHelper', array('separator' => '')),
                    array('Spinner'),
                    array('HtmlTag', array('tag' => 'div', 'class' => 'control-group form-controls'))
                ),
                'formnovalidate'    => 'formnovalidate'
            )
        );

        if ($this->getElement('btn_discover_domain')->isChecked() && isset($formData['resource'])) {
            $this->populateDomain(ResourceFactory::create($formData['resource']));
        }
    }

    /**
     * Discover the domain the LDAP server is responsible for and fill it in the form
     *
     * @param   LdapConnection  $connection
     */
    public function populateDomain(LdapConnection $connection)
    {
        try {
            $domain = $this->discoverDomain($connection);
        } catch (LdapException $e) {
            $this->_elements['btn_discover_domain']->addError($e->getMessage());
        }

        $this->_elements['domain']->setValue($domain);
    }

    /**
     * Discover the domain the LDAP server is responsible for
     *
     * @param   LdapConnection  $connection
     *
     * @return  string
     */
    protected function discoverDomain(LdapConnection $connection)
    {
        $cap = LdapCapabilities::discoverCapabilities($connection);

        if ($cap->isActiveDirectory()) {
            $netBiosName = $cap->getNetBiosName();
            if ($netBiosName !== null) {
                return $netBiosName;
            }
        }

        return $this->defaultNamingContextToFQDN($cap);
    }

    /**
     * Get the default naming context as FQDN
     *
     * @param   LdapCapabilities    $cap
     *
     * @return  string|null
     */
    protected function defaultNamingContextToFQDN(LdapCapabilities $cap)
    {
        $defaultNamingContext = $cap->getDefaultNamingContext();
        if ($defaultNamingContext !== null) {
            $validationMatches = array();
            if (preg_match('/\bdc=[^,]+(?:,dc=[^,]+)*$/', strtolower($defaultNamingContext), $validationMatches)) {
                $splitMatches = array();
                preg_match_all('/dc=([^,]+)/', $validationMatches[0], $splitMatches);
                return implode('.', $splitMatches[1]);
            }
        }
    }
}
