<?php

namespace App\Transformers\Owner;

use App\Models\Admin\OwnerDocument;
use App\Models\Admin\OwnerNeededDocument;
use App\Base\Constants\Masters\DriverDocumentStatus;
use App\Transformers\Owner\OwnerDocumentTransformer;
use App\Base\Constants\Masters\DriverDocumentStatusString;
use App\Transformers\Transformer;

class OwnerNeededDocumentTransformer extends Transformer
{
    /**
    * Resources that can be included if requested.
    *
    * @var array
    */
    protected array $availableIncludes = [
        'driver_document',
    ];
    /**
     * Resources that can be included default.
     *
     * @var array
     */
    protected array $defaultIncludes = [
        'driver_document',
    ];
    /**
     * A Fractal transformer.
     *
     * @param OwnerNeededDocument $ownerneededdocument
     * @return array
     */
    public function transform(OwnerNeededDocument $ownerneededdocument)
    {
        $userlang = 'en';
        if(auth()->user()){
            $userlang = auth()->user()->lang ?? 'en';
        }

        $params =  [
            'id'=>$ownerneededdocument->id,
            'name' => $ownerneededdocument->name,
            'doc_type' => $ownerneededdocument->doc_type,
            'has_identify_number' => (bool)$ownerneededdocument->has_identify_number,
            'has_expiry_date' => (bool) $ownerneededdocument->has_expiry_date,
            'active' => $ownerneededdocument->active,
            'identify_number_locale_key'=>$ownerneededdocument->identify_number_locale_key,
            'is_uploaded'=>false,
            'document_status'=>2,
            'is_required' => $ownerneededdocument->is_required == 1,
            'is_editable' => $ownerneededdocument->is_editable == 1,
            'document_status_string'=>custom_status_trans(DriverDocumentStatusString::NOT_UPLOADED,[],$userlang)
        ];

        $owner_document = OwnerDocument::where('document_id', $ownerneededdocument->id)->where('owner_id', auth()->user()->owner->id)->first();


        $params['is_uploaded'] = false;
        $params['is_front_and_back'] = false;


        if($ownerneededdocument->image_type=='front_and_back')
        {
            $params['is_front_and_back'] = true;
            $params['document_name_front'] = $ownerneededdocument->document_name_front;
            $params['document_name_back'] = $ownerneededdocument->document_name_back;

        }

        if ($owner_document) {
            $uploaded_status = [
                DriverDocumentStatus::UPLOADED_AND_APPROVED,
                DriverDocumentStatus::UPLOADED_AND_WAITING_FOR_APPROVAL,
                DriverDocumentStatus::REUPLOADED_AND_WAITING_FOR_APPROVAL,
            ];
            $params['is_uploaded'] = in_array($owner_document->document_status,$uploaded_status);
            $params['document_status']= $owner_document->document_status;

            foreach (DriverDocumentStatus::DocumentStatus() as $key=> $document_string) {
                if ($owner_document->document_status==$key) {
                    $params['document_status_string'] = custom_status_trans($document_string,[],$userlang);
                }
            }
        }

        return $params;
    }

    /**
     * Include the owner document of the owner needed document.
     *
     * @param OwnerNeededDocument $ownerneededdocument
     * @return \League\Fractal\Resource\Collection|\League\Fractal\Resource\NullResource
     */
    public function includeDriverDocument(OwnerNeededDocument $ownerneededdocument)
    {
        $document = $ownerneededdocument->ownerDocument()->where('owner_id', auth()->user()->owner->id)->first();

        return $document
        ? $this->item($document, new OwnerDocumentTransformer)
        : $this->null();
    }
}
