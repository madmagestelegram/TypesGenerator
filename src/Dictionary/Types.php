<?php

declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Dictionary;

class Types
{

    public const SKIP_TYPES = [
        'InputFile',
        'Sending files',
        'Inline mode objects',
        'Formatting options',
        'Inline mode methods',
        'CallbackGame',
        'ChatMember',
        'Determining list of commands',
    ];

    public const PARENT_ALIAS = [
        'PassportElementError' => Classes::PASSPORT_ERROR,
        'InputMedia' => Classes::INPUT_MEDIA,
        'InlineQueryResult' => Classes::INLINE_QUERY_RESULT,
        'InputMessageContent' => Classes::INPUT_MESSAGE_CONTENT,
        'InputFile' => Classes::INPUT_FILE,
        'ChatMember' => Classes::CHAT_MEMBER,
    ];

    public const ALIAS_TYPES = [
        'ChatMember' => [
            'ChatMemberOwner',
            'ChatMemberAdministrator',
            'ChatMemberMember',
            'ChatMemberRestricted',
            'ChatMemberLeft',
            'ChatMemberBanned',
        ],
        'PassportElementError' => [
            'PassportElementErrorDataField',
            'PassportElementErrorFrontSide',
            'PassportElementErrorReverseSide',
            'PassportElementErrorSelfie',
            'PassportElementErrorFile',
            'PassportElementErrorFiles',
            'PassportElementErrorTranslationFile',
            'PassportElementErrorTranslationFiles',
            'PassportElementErrorUnspecified',
        ],
        'InputMedia' => [
            'InputMediaAnimation',
            'InputMediaDocument',
            'InputMediaAudio',
            'InputMediaPhoto',
            'InputMediaVideo',
        ],
        'InlineQueryResult' => [
            'InlineQueryResultCachedAudio',
            'InlineQueryResultCachedDocument',
            'InlineQueryResultCachedGif',
            'InlineQueryResultCachedMpeg4Gif',
            'InlineQueryResultCachedPhoto',
            'InlineQueryResultCachedSticker',
            'InlineQueryResultCachedVideo',
            'InlineQueryResultCachedVoice',
            'InlineQueryResultArticle',
            'InlineQueryResultAudio',
            'InlineQueryResultContact',
            'InlineQueryResultGame',
            'InlineQueryResultDocument',
            'InlineQueryResultGif',
            'InlineQueryResultLocation',
            'InlineQueryResultMpeg4Gif',
            'InlineQueryResultPhoto',
            'InlineQueryResultVenue',
            'InlineQueryResultVideo',
            'InlineQueryResultVoice',
        ],
        'InputMessageContent' => [
            'InputTextMessageContent',
            'InputLocationMessageContent',
            'InputVenueMessageContent',
            'InputContactMessageContent',
        ],
    ];
}