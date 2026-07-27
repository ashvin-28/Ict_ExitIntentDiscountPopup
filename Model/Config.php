<?php

declare(strict_types=1);

namespace Ict\ExitIntentDiscountPopup\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PREFIX = 'exitintentpopup/';

    // Group paths
    private const XML_GENERAL   = self::XML_PREFIX . 'general/';
    private const XML_PROMOTION = self::XML_PREFIX . 'promotion/';
    private const XML_CONTENT   = self::XML_PREFIX . 'content/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    // -------------------------------------------------------------------------
    // General
    // -------------------------------------------------------------------------

    public function isEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_GENERAL . 'enable',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getMobileDelay(?string $scopeCode = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'mobile_delay',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getPopupFrequency(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'popup_frequency',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getBackgroundColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'background_color',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getFontColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'font_color',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getButtonColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'button_color',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getButtonTextColor(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_GENERAL . 'button_text_color',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
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
            $scopeCode
        );
    }

    public function getCopyButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PROMOTION . 'copy_button_text',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function isEmailToLoggedInEnabled(?string $scopeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PROMOTION . 'send_email_logged_in',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
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
            $scopeCode
        );
    }

    public function getPopupDescription(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'popup_description',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getDiscountLabel(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'discount_label',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getCtaButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'cta_button_text',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }

    public function getSecondaryButtonText(?string $scopeCode = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_CONTENT . 'secondary_button_text',
            ScopeInterface::SCOPE_STORE,
            $scopeCode
        );
    }
}
