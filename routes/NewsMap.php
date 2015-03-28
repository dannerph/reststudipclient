<?php

/**
 * NewsMap.php - Restroutes for the StudIP Client plugin News
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Florian Bieringer <florian.bieringer@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     ExtendedNews
 */
class CardMap extends RESTAPI\RouteMap {

    /**
     * Validates a user until a given date
     *
     * @put /card/validate/:username/:semester/:until/:status
     */
    public function validateCard($username, $semester, $until, $status) {
        
        // Lookup user
        $user = User::findByUsername($username);
        
        // If card doesnt exist
        if (!$user) {
            $this->error(404, "User not found");
        }

        $validation = new CardportalValidation(array($user->id, $semester));
        $validation->valid_until = $until;
        $validation->status = $status;
        $validation->store();
        
        // Remove all future validations
        CardportalValidation::deleteBySQL("user_id = ? AND valid_until > ?", array($user->id, $until));
    }

    /**
     * Validates a card until a given date
     *
     * @get /card/validate/:cardserialnumber
     */
    public function getValidation($cardserialnumber) {
        $card = CardportalCard::findBySQL('serialnumber = ?', array($cardserialnumber));

        // If card doesnt exist
        if (!$card) {
            $this->error(404, "Id not found");
        }

        $card = current($card);
        
        // If card is not validateable
        if (!$card->cardtype->validation) {
            $this->error(404, "Card not validateable");
        } 

        return array(
            'valid' => $card->getValidationDate() != "",
            'text' => $card->getValidationString(),
            'logo' => $card->getValidationDate() != "" && $card->cardtype->vbp_logo
        );
    }

    /**
     * Returns all printed cards
     *
     * @get /card/changed
     * @get /card/changed/:timestamp
     */
    public function getChanged($timestamp = null) {
        foreach (CardportalHelper::getChangedPrintedCards($timestamp) as $card) {
            $output[] = array(
                'id' => $card->id,
                'serialnumber' => $card->serialnumber,
                'cardnumber' => $card->cardnumber,
                'status' => $card->status,
                'user' => $card->user->username,
                'ub' => $card->library_id
            );
        }
        return $output;
    }

}

