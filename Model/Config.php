<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    private const XML_PREFIX = 'exitintentpopup/';

    // Group paths
    private const XML_GENERAL     = self::XML_PREFIX . 'general/';
    private const XML_PROMOTION   = self::XML_PREFIX . 'promotion/';
    private const XML_CONTENT     = self::XML_PREFIX . 'content/';
    private const XML_LOGIN_PROMPT = self::XML_PREFIX . 'login_prompt/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Resolve the store scope code, falling back to the current store when none is given.
     *
     * @param string|null $scopeCode
     * @return string
     */
    private function resolveStore(?string $scopeCode): string
    {
        return $scopeCode ?? (string) $this->storeManager->getStore()->getId();
    }

    // -------------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------------

    /**
     * Check whether the popup is enabled.
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_GENERAL . 'enable',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the mobile inactivity delay, in milliseconds.
     *
     * @param string|null $scopeCode
     * @return int
     */
    public function getMobileDelay(?string $scopeCode = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'mobile_delay',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the configured popup display frequency.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getPopupFrequency(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'popup_frequency',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the popup background color.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getBackgroundColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'background_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the popup font color.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getFontColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'font_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the popup button color.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getButtonColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'button_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the popup button text color.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getButtonTextColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'button_text_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    // -------------------------------------------------------------------------
    // Promotion
    // -------------------------------------------------------------------------

    /**
     * Get the configured Cart Price Rule ID.
     *
     * @return int
     */
    public function getCouponRuleId(): int
    {
        // coupon_rule_id is showInWebsite="0" showInStore="0" — global-only field.
        return (int) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'coupon_rule_id',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    /**
     * Get the copy-to-clipboard button text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getCopyButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'copy_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the apply-to-cart button text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getApplyButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'apply_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    // -------------------------------------------------------------------------
    // Content
    // -------------------------------------------------------------------------

    /**
     * Get the popup heading text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getPopupHeading(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'popup_heading',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the popup description text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getPopupDescription(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'popup_description',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the discount label text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getDiscountLabel(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'discount_label',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the close button text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getCloseButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'close_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    // -------------------------------------------------------------------------
    // Login Prompt
    // -------------------------------------------------------------------------

    /**
     * Get the configured login prompt display frequency.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getLoginPromptFrequency(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOGIN_PROMPT . 'frequency',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the login prompt heading text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getLoginPromptHeading(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOGIN_PROMPT . 'heading',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the login prompt message text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getLoginPromptMessage(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOGIN_PROMPT . 'message',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the login button text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getLoginButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOGIN_PROMPT . 'login_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    /**
     * Get the register link text.
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getRegisterLinkText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOGIN_PROMPT . 'register_link_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }
}
