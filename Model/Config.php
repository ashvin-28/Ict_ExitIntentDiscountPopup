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
    private const XML_GENERAL   = self::XML_PREFIX . 'general/';
    private const XML_PROMOTION = self::XML_PREFIX . 'promotion/';
    private const XML_CONTENT   = self::XML_PREFIX . 'content/';

    public function __construct(
        private readonly ScopeConfigInterface  $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {}

    private function resolveStore(?string $scopeCode): string
    {
        return $scopeCode ?? (string) $this->storeManager->getStore()->getId();
    }

    // -------------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------------

    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_GENERAL . 'enable',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getMobileDelay(?string $scopeCode = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'mobile_delay',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getPopupFrequency(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'popup_frequency',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getBackgroundColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'background_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getFontColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'font_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getButtonColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'button_color',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

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

    public function getCouponRuleId(?string $scopeCode = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'coupon_rule_id',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getCopyButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'copy_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function isEmailToLoggedInEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PROMOTION . 'send_email_logged_in',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    // -------------------------------------------------------------------------
    // Content
    // -------------------------------------------------------------------------

    public function getPopupHeading(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'popup_heading',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getPopupDescription(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'popup_description',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getDiscountLabel(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'discount_label',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getCtaButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'cta_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }

    public function getSecondaryButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'secondary_button_text',
            ScopeInterface::SCOPE_STORE,
            $this->resolveStore($scopeCode)
        );
    }
}
