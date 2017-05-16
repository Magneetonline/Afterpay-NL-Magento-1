<?php 
/**
 * Copyright (c) 2011-2017  arvato Finance B.V.
 *
 * AfterPay reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of AfterPay.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category    AfterPay
 * @package     Afterpay_Afterpay
 * @copyright   Copyright (c) 2011-2017 arvato Finance B.V.
 */
 
 class Afterpay_Afterpay_Block_RejectMessages extends Mage_Core_Block_Abstract
{
    protected $_rejectTemplate;
    
    public $template1 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Dit kan om diverse (tijdelijke) redenen zijn. 
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
    ";
    
    public $template29 = "
        <p>
            Hartelijk welkom bij AfterPay. 
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            AfterPay hanteert voor voor eerste gebruikers een instapbedrag. 
            Uw huidige orderbedrag overstijgt het instapbedrag. 
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
    ";
    
    public $template30 = "
        <p>
            Hartelijk welkom bij AfterPay. 
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Graag wil AfterPay uw betaalverzoek accepteren, echter volgens onze administratie heeft u het maximale aantal openstaande betalingen bereikt. 
            Indien u tot betaling overgaat zijn wij u graag weer snel van dienst.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
    ";
    
    public $template36 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is het opgegeven e-mailadres volgens onze bronnen niet volledig of bestaat het niet. 
            Indien u van AfterPay gebruik wilt maken, dient u gebruik te maken van een geldig en actief e-mailadres.
        </p>
        <p>
            Wij adviseren u om een geldig en actief e-mailadres te gebruiken bij uw bestelling.
        </p>
    ";
    
    public $template40 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is uw leeftijd onder de 18 jaar. 
            Indien u gebruik wilt maken van AfterPay dient uw leeftijd minimaal 18 jaar of ouder te zijn.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
    ";
    
    public $template42 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is uw adres informatie niet correct of niet compleet. 
            Indien u van AfterPay gebruik wilt maken, dient het opgegeven adres een geldig woon/verblijf plaats te zijn.
        </p>
        <p>
            Wij adviseren u om een correct woon/verblijf plaats in te vullen bij uw bestelling.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
    ";
    
    public $template71 = "
        <p>
            Hartelijk welkom bij AfterPay. 
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas kunnen wij uw kamer van koophandel dossier niet raadplegen. 
            Dit kan als oorzaak hebben dat uw KVK nummer niet juist is en/of de bedrijfsnaam die u ingegegeven heeft niet overeenkomt met hetgeen geregistreerd staat bij de kamer van koophandel.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.nl/nl/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
        <p>
            Wij adviseren u om uw aanvraag gegevens te corrigeren en het opnieuw te proberen.
        </p>
    ";
    
    public $templatebe1 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Dit kan om diverse (tijdelijke) redenen zijn. 
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.be/be/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
    ";
    
    public $templatebe29 = "
        <p>
            Hartelijk welkom bij AfterPay. 
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            AfterPay hanteert voor voor eerste gebruikers een instapbedrag. 
            Uw huidige orderbedrag overstijgt het instapbedrag. 
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.be/be/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
    ";
    
    public $templatebe30 = "
        <p>
            Hartelijk welkom bij AfterPay. 
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Graag wil AfterPay uw betaalverzoek accepteren, echter volgens onze administratie heeft u het maximale aantal openstaande betalingen bereikt. 
            Indien u tot betaling overgaat zijn wij u graag weer snel van dienst.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.be/be/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
    ";
    
    public $templatebe36 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is het opgegeven e-mailadres volgens onze bronnen niet volledig of bestaat het niet. 
            Indien u van AfterPay gebruik wilt maken, dient u gebruik te maken van een geldig en actief e-mailadres.
        </p>
        <p>
            Wij adviseren u om een geldig en actief e-mailadres te gebruiken bij uw bestelling.
        </p>
    ";
    
    public $templatebe40 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is uw leeftijd onder de 18 jaar. 
            Indien u gebruik wilt maken van AfterPay dient uw leeftijd minimaal 18 jaar of ouder te zijn.
        </p>
        <p>
            Wij adviseren u voor een andere betaalmethode te kiezen om alsnog de betaling van uw bestelling af te ronden.
        </p>
    ";
    
    public $templatebe42 = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling achteraf te betalen op dit moment niet door AfterPay wordt geaccepteerd. 
            Helaas is uw adres informatie niet correct of niet compleet. 
            Indien u van AfterPay gebruik wilt maken, dient het opgegeven adres een geldig woon/verblijf plaats te zijn.
        </p>
        <p>
            Wij adviseren u om een correct woon/verblijf plaats in te vullen bij uw bestelling.
        </p>
        <p>
            Voor vragen over uw afwijzing kunt u op de website van AfterPay kijken via de link <a href=\"https://www.afterpay.be/be/consumenten/vraag-en-antwoord/\" target=\"_blank\">Klantenservice van AfterPay</a>.
        </p>
    ";

    public $templatede = "
        <p>
            Die ausgewählte Bezahlart von Rechnungskauf steht Ihnen aus verschiedenen Gründen leider nicht zur Verfügung.
        </p>
        <p>
            Wir empfehlen Ihnen, Ihre Zahlung mit einer alternativen Zahlungsmethode abzuschließen, so dass Ihre Bestellung durchgeführt werden kann.
        </p>
    ";
    
    public $templatebp = "
        <p>
            Het spijt ons u te moeten mededelen dat uw aanvraag om uw bestelling te betalen met Mijn Betaalplan op dit moment niet wordt geaccepteerd. 
        </p>

    ";
    
    public function setRejectTemplate($id = 1) {
        $templateId = 'template' . $id;
        
        $this->_rejectTemplate = $this->template1;
        
        if (isset($this->$templateId)) {
            $this->_rejectTemplate = $this->$templateId;
        }
        
        return $this;
    }
    
    protected function _toHtml()
    {
        return $this->_rejectTemplate;
    }
}