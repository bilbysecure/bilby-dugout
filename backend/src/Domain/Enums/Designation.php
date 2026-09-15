<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** Agency-staff designations. `operations_manager` carries manager-tier visibility. */
enum Designation: string
{
    case Admin                      = 'admin';
    case OperationsManager          = 'operations_manager';
    case AccountManager             = 'account_manager';
    case CreativeDirector           = 'creative_director';
    case GraphicDesigner            = 'graphic_designer';
    case SocialMediaManager         = 'social_media_manager';
    case DigitalMarketingSpecialist = 'digital_marketing_specialist';
    case ContentCopywriter          = 'content_copywriter';
    case VideoEditorGraphicDesigner = 'video_editor_graphic_designer';

    public const VALUES = [
        'admin', 'operations_manager', 'account_manager', 'creative_director',
        'graphic_designer', 'social_media_manager', 'digital_marketing_specialist',
        'content_copywriter', 'video_editor_graphic_designer',
    ];
}
