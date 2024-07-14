<?php

declare(strict_types=1);

namespace MadmagesTelegram\TypesGenerator\Dictionary;

class Types
{

    public const INPUT_FILE = 'InputFile';

    public const ALLOWED_EMPTY_TYPES = [
        self::INPUT_FILE,
        'ForumTopicClosed',
        'ForumTopicReopened',
        'GeneralForumTopicHidden',
        'GeneralForumTopicUnhidden',
        'VideoChatStarted',
        'GiveawayCreated',
    ];

    public const SKIP_TYPES = [
        'Sending files',
        'Inline mode objects',
        'Formatting options',
        'Inline mode methods',
        'CallbackGame',
        'Accent colors',
        'Profile accent colors',
        'Determining list of commands',
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
            'InlineQueryResultsButton',
        ],
        'InputMessageContent' => [
            'InputTextMessageContent',
            'InputLocationMessageContent',
            'InputVenueMessageContent',
            'InputContactMessageContent',
            'InputInvoiceMessageContent',
        ],
        'MenuButton' => [
            'MenuButtonCommands',
            'MenuButtonWebApp',
            'MenuButtonDefault',
        ],
        'BackgroundFill' => [
            'BackgroundFillSolid',
            'BackgroundFillGradient',
            'BackgroundFillFreeformGradient',
        ],
        'MaybeInaccessibleMessage' => [
            'Message',
            'InaccessibleMessage',
        ],
        'MessageOrigin' => [
            'MessageOriginUser',
            'MessageOriginHiddenUser',
            'MessageOriginChat',
            'MessageOriginChannel',
        ],
        'PaidMedia' => [
            'PaidMediaPreview',
            'PaidMediaPhoto',
            'PaidMediaVideo',
        ],
        'BackgroundType' => [
            'BackgroundTypeFill',
            'BackgroundTypeWallpaper',
            'BackgroundTypePattern',
            'BackgroundTypeChatTheme',
        ],
        'ReactionType' => [
            'ReactionTypeEmoji',
            'ReactionTypeCustomEmoji',
        ],
        'BotCommandScope' => [
            'BotCommandScopeDefault',
            'BotCommandScopeAllPrivateChats',
            'BotCommandScopeAllGroupChats',
            'BotCommandScopeAllChatAdministrators',
            'BotCommandScopeChat',
            'BotCommandScopeChatAdministrators',
            'BotCommandScopeChatMember',
        ],
        'ChatBoostSource' => [
            'ChatBoostSourcePremium',
            'ChatBoostSourceGiftCode',
            'ChatBoostSourceGiveaway',
        ],
        'InputPaidMedia' => [
            'InputPaidMediaPhoto',
            'InputPaidMediaVideo',
        ],
        'RevenueWithdrawalState' => [
            'RevenueWithdrawalStatePending',
            'RevenueWithdrawalStateSucceeded',
            'RevenueWithdrawalStateFailed',
        ],
        'TransactionPartner' => [
            'TransactionPartnerUser',
            'TransactionPartnerFragment',
            'TransactionPartnerTelegramAds',
            'TransactionPartnerOther',
        ],
    ];
}