<?php

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-present David Cole <david.cole1340@gmail.com>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Parts\Guild;

use Carbon\Carbon;
use Discord\Exceptions\FileNotFoundException;
use Discord\Helpers\Collection;
use Discord\Http\Endpoint;
use Discord\Http\Exceptions\NoPermissionsException;
use Discord\Parts\Channel\StageInstance;
use Discord\Parts\Part;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Repository\Guild\BanRepository;
use Discord\Repository\Guild\ChannelRepository;
use Discord\Repository\Guild\EmojiRepository;
use Discord\Repository\Guild\InviteRepository;
use Discord\Repository\Guild\MemberRepository;
use Discord\Repository\Guild\RoleRepository;
use Discord\Parts\Guild\AuditLog\AuditLog;
use Discord\Parts\Guild\AuditLog\Entry;
use Discord\Repository\Guild\GuildCommandRepository;
use Discord\Repository\Guild\ScheduledEventRepository;
use Discord\Repository\Guild\GuildTemplateRepository;
use Discord\Repository\Guild\StickerRepository;
use Discord\Repository\Guild\StageInstanceRepository;
use Exception;
use React\Promise\ExtendedPromiseInterface;
use ReflectionClass;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A Guild is Discord's equivalent of a server. It contains all the Members, Channels, Roles, Bans etc.
 *
 * @property string             $id                                       The unique identifier of the guild.
 * @property string             $name                                     The name of the guild.
 * @property string             $icon                                     The URL to the guild icon.
 * @property string             $icon_hash                                The icon hash for the guild.
 * @property string             $region                                   The region the guild's voice channels are hosted in.
 * @property User               $owner                                    The owner of the guild.
 * @property string             $owner_id                                 The unique identifier of the owner of the guild.
 * @property Carbon             $joined_at                                A timestamp of when the current user joined the guild.
 * @property string             $afk_channel_id                           The unique identifier of the AFK channel ID.
 * @property int                $afk_timeout                              How long you will remain in the voice channel until you are moved into the AFK channel.
 * @property string[]           $features                                 An array of features that the guild has.
 * @property string             $splash                                   The URL to the guild splash.
 * @property string             $discovery_splash                         Discovery splash hash. Only for discoverable guilds.
 * @property string             $splash_hash                              The splash hash for the guild.
 * @property bool               $large                                    Whether the guild is considered 'large' (over 250 members).
 * @property int                $verification_level                       The verification level used for the guild.
 * @property int                $member_count                             How many members are in the guild.
 * @property int                $default_message_notifications            Default notification level.
 * @property int                $explicit_content_filter                  Explicit content filter level.
 * @property int                $mfa_level                                MFA level required to join.
 * @property string             $application_id                           Application that made the guild, if made by one.
 * @property bool               $widget_enabled                           Is server widget enabled.
 * @property string             $widget_channel_id                        Channel that the widget will create an invite to.
 * @property string             $system_channel_id                        Channel that system notifications are posted in.
 * @property int                $system_channel_flags                     Flags for the system channel.
 * @property string             $rules_channel_id                         Channel that the rules are in.
 * @property object[]           $voice_states                             Array of voice states.
 * @property int                $max_presences                            Maximum amount of presences allowed in the guild.
 * @property int                $max_members                              Maximum amount of members allowed in the guild.
 * @property string             $vanity_url_code                          Vanity URL code for the guild.
 * @property string             $description                              Guild description if it is discoverable.
 * @property string             $banner                                   Banner hash.
 * @property int                $premium_tier                             Server boost level.
 * @property int                $premium_subscription_count               Number of boosts in the guild.
 * @property string             $preferred_locale                         Preferred locale of the guild.
 * @property string             $public_updates_channel_id                Notice channel id.
 * @property int                $max_video_channel_users                  Maximum amount of users allowed in a video channel.
 * @property int                $approximate_member_count
 * @property int                $approximate_presence_count
 * @property WelcomeScreen      welcome_screen
 * @property int                $nsfw_level                               The guild NSFW level.
 * @property bool               $premium_progress_bar_enabled             Whether the guild has the boost progress bar enabled.
 * @property bool               $feature_animated_icon                    guild has access to set an animated guild icon.
 * @property bool               $feature_banner                           guild has access to set a guild banner image.
 * @property bool               $feature_commerce                         guild has access to use commerce features (create store channels).
 * @property bool               $feature_community                        guild can enable welcome screen, Membership Screening, stage channels and discovery, and receives community updates.
 * @property bool               $feature_discoverable                     guild is able to be discovered in the directory.
 * @property bool               $feature_featurable                       guild is able to be featured in the directory.
 * @property bool               $feature_invite_splash                    guild has access to set an invite splash background.
 * @property bool               $feature_member_verification_gate_enabled guild has enabled membership screening.
 * @property bool               $feature_news                             guild has access to create news channels.
 * @property bool               $feature_partnered                        guild is partnered.
 * @property bool               $feature_preview_enabled                  guild can be previewed before joining via membership screening or the directory.
 * @property bool               $feature_vanity_url                       guild has access to set a vanity url.
 * @property bool               $feature_verified                         guild is verified.
 * @property bool               $feature_vip_regions                      guild has access to set 384kbps bitrate in voice.
 * @property bool               $feature_welcome_screen_enabled           guild has enabled the welcome screen.
 * @property bool               $feature_ticketed_events_enabled          guild has enabled ticketed events.
 * @property bool               $feature_monetization_enabled             guild has enabled monetization.
 * @property bool               $feature_more_stickers                    guild has increased custom sticker slots.
 * @property bool               $feature_three_day_thread_archive         guild has access to the three day archive time for threads.
 * @property bool               $feature_seven_day_thread_archive         guild has access to the seven day archive time for threads.
 * @property bool               $feature_private_threads                  guild has access to create private threads.
 * @property bool               $feature_role_icons                       guild is able to set role icons.
 * @property RoleRepository     $roles
 * @property ChannelRepository  $channels
 * @property MemberRepository   $members
 * @property InviteRepository   $invites
 * @property BanRepository      $bans
 * @property EmojiRepository    $emojis
 * @property GuildCommandRepository $commands
 * @property StickerRepository $stickers
 * @property StageInstanceRepository $stage_instances
 * @property GuildTemplateRepository $templates
 * @property ScheduledEventRepository $guild_scheduled_events
 */
class Guild extends Part
{
    public const REGION_DEFAULT = 'us_west';

    public const LEVEL_OFF = 0;
    public const LEVEL_LOW = 1;
    public const LEVEL_MEDIUM = 2;
    public const LEVEL_TABLEFLIP = 3;
    public const LEVEL_DOUBLE_TABLEFLIP = 4;

    public const SUPPRESS_JOIN_NOTIFICATIONS = (1 << 0);
    public const SUPPRESS_PREMIUM_SUBSCRIPTION = (1 << 1);
    public const SUPPRESS_GUILD_REMINDER_NOTIFICATIONS = (1 << 2);
    public const SUPPRESS_JOIN_NOTIFICATION_REPLIES = (1 << 3);

    public const NSFW_DEFAULT = 0;
    public const NSFW_EXPLICIT = 1;
    public const NSFW_SAFE = 2;
    public const NSFW_AGE_RESTRICTED = 3;

    /**
     * @inheritdoc
     */
    protected $fillable = [
        'id',
        'name',
        'icon',
        'icon_hash',
        'region',
        'owner_id',
        'roles',
        'joined_at',
        'afk_channel_id',
        'afk_timeout',
        'features',
        'splash',
        'discovery_splash',
        'emojis',
        'large',
        'verification_level',
        'member_count',
        'default_message_notifications',
        'explicit_content_filter',
        'mfa_level',
        'application_id',
        'widget_enabled',
        'widget_channel_id',
        'system_channel_id',
        'system_channel_flags',
        'rules_channel_id',
        'voice_states',
        'max_presences',
        'max_members',
        'vanity_url_code',
        'description',
        'banner',
        'premium_tier',
        'premium_subscription_count',
        'preferred_locale',
        'public_updates_channel_id',
        'max_video_channel_users',
        'approximate_member_count',
        'approximate_presence_count',
        'welcome_screen',
        'nsfw_level',
        'stage_instances',
        'premium_progress_bar_enabled',
    ];

    /**
     * @inheritDoc
     */
    protected $visible = [
        'feature_animated_icon',
        'feature_banner',
        'feature_commerce',
        'feature_community',
        'feature_discoverable',
        'feature_featurable',
        'feature_invite_splash',
        'feature_member_verification_gate_enabled',
        'feature_news',
        'feature_partnered',
        'feature_preview_enabled',
        'feature_vanity_url',
        'feature_verified',
        'feature_vip_regions',
        'feature_welcome_screen_enabled',
        'feature_ticketed_events_enabled',
        'feature_monetization_enabled',
        'feature_more_stickers',
        'feature_three_day_thread_archive',
        'feature_seven_day_thread_archive',
        'feature_private_threads',
        'feature_role_icons',
    ];

    /**
     * @inheritdoc
     */
    protected $repositories = [
        'members' => MemberRepository::class,
        'roles' => RoleRepository::class,
        'channels' => ChannelRepository::class,
        'bans' => BanRepository::class,
        'invites' => InviteRepository::class,
        'emojis' => EmojiRepository::class,
        'stickers' => StickerRepository::class,
        'stage_instances' => StageInstanceRepository::class,
        'templates' => GuildTemplateRepository::class,
        'guild_scheduled_events' => ScheduledEventRepository::class,
    ];

    /**
     * An array of valid regions.
     *
     * @var Collection|null
     */
    protected $regions;

    /**
     * Returns the channels invites.
     *
     * @return ExtendedPromiseInterface
     * @throws \Exception
     */
    public function getInvites(): ExtendedPromiseInterface
    {
        return $this->http->get(Endpoint::bind(Endpoint::GUILD_INVITES, $this->id))->then(function ($response) {
            $invites = new Collection();

            foreach ($response as $invite) {
                $invite = $this->factory->create(Invite::class, $invite, true);
                $invites->push($invite);
            }

            return $invites;
        });
    }

    /**
     * Unbans a member. Alias for `$guild->bans->unban($user)`.
     *
     * @param User|string $user
     *
     * @return ExtendedPromiseInterface
     */
    public function unban($user): ExtendedPromiseInterface
    {
        return $this->bans->unban($user);
    }

    /**
     * Returns the owner.
     *
     * @return ExtendedPromiseInterface
     */
    protected function getOwnerAttribute()
    {
        return $this->discord->users->get('id', $this->owner_id);
    }

    /**
     * Returns the joined_at attribute.
     *
     * @return Carbon|null The joined_at attribute.
     * @throws \Exception
     */
    protected function getJoinedAtAttribute()
    {
        if (!array_key_exists('joined_at', $this->attributes)) {
            return null;
        }

        return new Carbon($this->attributes['joined_at']);
    }

    /**
     * Returns the guilds icon.
     *
     * @param string|null $format The image format.
     * @param int         $size   The size of the image.
     *
     * @return string|null The URL to the guild icon or null.
     */
    public function getIconAttribute(string $format = null, int $size = 1024)
    {
        if (!isset($this->attributes['icon'])) {
            return null;
        }

        if (isset($format)) {
            $allowed = ['png', 'jpg', 'webp', 'gif'];
            if (!in_array(strtolower($format), $allowed)) {
                $format = 'webp';
            }
        } elseif (strpos($this->attributes['icon'], 'a_') === 0) {
            $format = 'gif';
        } else {
            $format = 'webp';
        }

        return "https://cdn.discordapp.com/icons/{$this->id}/{$this->attributes['icon']}.{$format}?size={$size}";
    }

    /**
     * Returns the guild icon hash.
     *
     * @return string|null The guild icon hash or null.
     */
    protected function getIconHashAttribute()
    {
        return $this->attributes['icon_hash'] ?? $this->attributes['icon'];
    }

    /**
     * Returns the guild splash.
     *
     * @param string $format The image format.
     * @param int    $size   The size of the image.
     *
     * @return string|null The URL to the guild splash or null.
     */
    public function getSplashAttribute(string $format = 'webp', int $size = 2048)
    {
        if (!isset($this->attributes['splash'])) {
            return null;
        }

        $allowed = ['png', 'jpg', 'webp'];

        if (!in_array(strtolower($format), $allowed)) {
            $format = 'webp';
        }

        return "https://cdn.discordapp.com/splashes/{$this->id}/{$this->attributes['splash']}.{$format}?size={$size}";
    }

    /**
     * Returns the guild splash hash.
     *
     * @return string|null The guild splash hash or null.
     */
    protected function getSplashHashAttribute()
    {
        return $this->attributes['splash'];
    }

    protected function getFeatureAnimatedIconAttribute(): bool
    {
        return in_array('ANIMATED_ICON', $this->features);
    }

    protected function getFeatureBannerAttribute(): bool
    {
        return in_array('BANNER', $this->features);
    }

    protected function getFeatureCommerceAttribute(): bool
    {
        return in_array('COMMERCE', $this->features);
    }

    protected function getFeatureDiscoverableAttribute(): bool
    {
        return in_array('DISCOVERABLE', $this->features);
    }

    protected function getFeatureFeaturableAttribute(): bool
    {
        return in_array('FEATURABLE', $this->features);
    }

    protected function getFeatureInviteSplashAttribute(): bool
    {
        return in_array('INVITE_SPLASH', $this->features);
    }

    protected function getFeatureMemberVerificationGateEnabledAttribute(): bool
    {
        return in_array('MEMBER_VERIFICATION_GATE_ENABLED', $this->features);
    }

    protected function getFeatureNewsAttribute(): bool
    {
        return in_array('NEWS', $this->features);
    }

    protected function getFeaturePartneredAttribute(): bool
    {
        return in_array('PARTNERED', $this->features);
    }

    protected function getFeaturePreviewEnabledAttribute(): bool
    {
        return in_array('PREVIEW_ENABLED', $this->features);
    }

    protected function getFeatureVanityUrlAttribute(): bool
    {
        return in_array('VANITY_URL', $this->features);
    }

    protected function getFeatureVerifiedAttribute(): bool
    {
        return in_array('VERIFIED', $this->features);
    }

    protected function getFeatureVipRegionsAttribute(): bool
    {
        return in_array('VIP_REGIONS', $this->features);
    }

    protected function getFeatureWelcomeScreenEnabledAttribute(): bool
    {
        return in_array('WELCOME_SCREEN_ENABLED', $this->features);
    }

    protected function getFeatureTicketedEventsEnabledAttribute(): bool
    {
        return in_array('TICKETED_EVENTS_ENABLED', $this->features);
    }

    protected function getFeatureMonetizationEnabledAttribute(): bool
    {
        return in_array('MONETIZATION_ENABLED', $this->features);
    }

    protected function getFeatureMoreStickersAttribute(): bool
    {
        return in_array('MORE_STICKERS', $this->features);
    }

    protected function getFeatureThreeDayThreadArchiveAttribute(): bool
    {
        return in_array('THREE_DAY_THREAD_ARCHIVE', $this->features);
    }

    protected function getFeatureSevenDayThreadArchiveAttribute(): bool
    {
        return in_array('SEVEN_DAY_THREAD_ARCHIVE', $this->features);
    }

    protected function getFeaturePrivateThreadsAttribute(): bool
    {
        return in_array('PRIVATE_THREADS', $this->features);
    }

    protected function getFeatureRoleIconsAttribute(): bool
    {
        return in_array('ROLE_ICONS', $this->features);
    }

    /**
     * Gets the voice regions available.
     *
     * @return ExtendedPromiseInterface
     */
    public function getVoiceRegions(): ExtendedPromiseInterface
    {
        if (!is_null($this->regions)) {
            return \React\Promise\resolve($this->regions);
        }

        return $this->http->get('voice/regions')->then(function ($regions) {
            $regions = new Collection($regions);

            $this->regions = $regions;

            return $regions;
        });
    }

    /**
     * Creates a role.
     *
     * @param array $data The data to fill the role with.
     *
     * @return ExtendedPromiseInterface
     * @throws \Exception
     */
    public function createRole(array $data = []): ExtendedPromiseInterface
    {
        $botperms = $this->members->offsetGet($this->discord->id)->getPermissions();
        if (!$botperms->manage_roles) {
            return \React\Promise\reject(new NoPermissionsException('You do not have permission to manage roles in the specified guild.'));
        }
        return $this->roles->save($this->factory->create(Role::class, $data));
    }

    /**
     * Creates an Emoji for the guild.
     *
     * @param array $options        An array of options.
     *                              name => name of the emoji
     *                              image => the 128x128 emoji image
     *                              roles => roles allowed to use this emoji
     * @param string|null $filepath The path to the file if specified will override image data string.
     * @param string|null $reason   Reason for Audit Log.
     *
     * @throws FileNotFoundException Thrown when the file does not exist.
     *
     * @return ExtendedPromiseInterface<Emoji>
     */
    public function createEmoji(array $options, ?string $filepath = null, ?string $reason = null): ExtendedPromiseInterface
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefined([
                'name',
                'image',
                'roles',
            ])
            ->setRequired('name')
            ->setAllowedTypes('name', 'string')
            ->setAllowedTypes('image', 'string')
            ->setAllowedTypes('roles', 'array')
            ->setDefault('roles', []);

        $options = $resolver->resolve($options);

        if (isset($filepath)) {
            if (!file_exists($filepath)) {
                throw new FileNotFoundException("File does not exist at path {$filepath}.");
            }

            $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            if ($extension == 'jpg') $extension = 'jpeg';
            $contents = file_get_contents($filepath);

            $options['image'] = "data:image/{$extension};base64," . base64_encode($contents);
        }

        $headers = [];
        if (isset($reason)) {
            $headers['X-Audit-Log-Reason'] = $reason;
        }

        return $this->http->post(Endpoint::bind(Endpoint::GUILD_EMOJIS, $this->id), $options, $headers)
            ->then(function ($response) {
                $emoji = $this->factory->create(Emoji::class, $response, true);
                $this->emojis->push($emoji);

                return $emoji;
            });
    }

    /**
     * Leaves the guild.
     *
     * @return ExtendedPromiseInterface
     */
    public function leave(): ExtendedPromiseInterface
    {
        return $this->discord->guilds->leave($this->id);
    }

    /**
     * Transfers ownership of the guild to
     * another member.
     *
     * @param Member|int  $member The member to transfer ownership to.
     * @param string|null $reason Reason for Audit Log.
     *
     * @return ExtendedPromiseInterface
     */
    public function transferOwnership($member, ?string $reason = null): ExtendedPromiseInterface
    {
        if ($member instanceof Member) {
            $member = $member->id;
        }

        $headers = [];
        if (isset($reason)) {
            $headers['X-Audit-Log-Reason'] = $reason;
        }

        return $this->http->patch(Endpoint::bind(Endpoint::GUILD), ['owner_id' => $member], $headers)->then(function ($response) use ($member) {
            if ($response->owner_id != $member) {
                throw new Exception('Ownership was not transferred correctly.');
            }

            return $this;
        });
    }

    /**
     * Validates the specified region.
     *
     * @return ExtendedPromiseInterface
     *
     * @see self::REGION_DEFAULT The default region.
     */
    public function validateRegion(): ExtendedPromiseInterface
    {
        return $this->getVoiceRegions()->then(function () {
            $regions = $this->regions->map(function ($region) {
                return $region->id;
            })->toArray();

            if (!in_array($this->region, $regions)) {
                return self::REGION_DEFAULT;
            }

            return $this->region;
        });
    }

    /**
     * Returns an audit log object for the query.
     *
     * @param array $options An array of options.
     *                       user_id => filter the log for actions made by a user
     *                       action_type => the type of audit log event
     *                       before => filter the log before a certain entry id
     *                       limit => how many entries are returned (default 50, minimum 1, maximum 100)
     *
     * @return ExtendedPromiseInterface
     */
    public function getAuditLog(array $options = []): ExtendedPromiseInterface
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined([
            'user_id',
            'action_type',
            'before',
            'limit',
        ])
            ->setAllowedTypes('user_id', ['string', 'int', Member::class, User::class])
            ->setAllowedTypes('action_type', 'int')
            ->setAllowedTypes('before', ['string', 'int', Entry::class])
            ->setAllowedTypes('limit', 'int')
            ->setAllowedValues('action_type', array_values((new ReflectionClass(Entry::class))->getConstants()))
            ->setAllowedValues('limit', range(1, 100));

        $options = $resolver->resolve($options);

        if ($options['user_id'] ?? null instanceof Part) {
            $options['user_id'] = $options['user_id']->id;
        }

        if ($options['before'] ?? null instanceof Part) {
            $options['before'] = $options['before']->id;
        }

        $endpoint = Endpoint::bind(Endpoint::AUDIT_LOG, $this->id);

        foreach ($options as $key => $value) {
            $endpoint->addQuery($key, $value);
        }

        return $this->http->get($endpoint)->then(function ($response) {
            $response = (array) $response;
            $response['guild_id'] = $this->id;

            return $this->factory->create(AuditLog::class, $response, true);
        });
    }

    /**
     * Updates the positions of a list of given roles.
     *
     * The `$roles` array should be an associative array where the LHS key is the position,
     * and the RHS value is a `Role` object or a string ID, e.g. [1 => 'role_id_1', 3 => 'role_id_3'].
     *
     * @param array $roles
     *
     * @return ExtendedPromiseInterface
     */
    public function updateRolePositions(array $roles): ExtendedPromiseInterface
    {
        $payload = [];

        foreach ($roles as $position => $role) {
            $payload[] = [
                'id' => ($role instanceof Role) ? $role->id : $role,
                'position' => $position,
            ];
        }

        return $this->http->patch(Endpoint::bind(Endpoint::GUILD_ROLES, $this->id), $payload)
            ->then(function () {
                return $this;
            });
    }

    /**
     * Returns a list of guild member objects whose username or nickname starts with a provided string.
     *
     * @param array $options An array of options.
     *                       query => query string to match username(s) and nickname(s) against
     *                       limit => how many entries are returned (default 1, minimum 1, maximum 1000)
     *
     * @return ExtendedPromiseInterface
     */
    public function searchMembers(array $options): ExtendedPromiseInterface
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined([
            'query',
            'limit',
        ])
            ->setDefaults(['limit' => 1])
            ->setAllowedTypes('query', 'string')
            ->setAllowedTypes('limit', 'int')
            ->setAllowedValues('limit', range(1, 1000));

        $options = $resolver->resolve($options);

        $endpoint = Endpoint::bind(Endpoint::GUILD_MEMBERS_SEARCH, $this->id);
        $endpoint->addQuery('query', $options['query']);
        $endpoint->addQuery('limit', $options['limit']);

        return $this->http->get($endpoint)->then(function ($responses) {
            $members = new Collection();

            foreach ($responses as $response) {
                if (!$member = $this->members->get('id', $response->user->id)) {
                    $member = $this->factory->create(Member::class, $response, true);
                    $this->members->push($member);
                }

                $members->push($member);
            }

            return $members;
        });
    }

    /**
     * Get the Welcome Screen for the guild.
     *
     * @param bool $fresh Whether we should skip checking the cache.
     *
     * @return ExtendedPromiseInterface
     */
    public function getWelcomeScreen(bool $fresh = false): ExtendedPromiseInterface
    {
        if (!$fresh && isset($this->attributes['welcome_screen'])) {
            return \React\Promise\resolve($this->discord->factory(WelcomeScreen::class, $this->attributes['welcome_screen'], true));
        }

        return $this->http->get(Endpoint::bind(Endpoint::GUILD_WELCOME_SCREEN, $this->id))->then(function ($response) {
            $welcome_screen = $this->discord->factory(WelcomeScreen::class, $response, true);
            $this->attributes['welcome_screen'] = $welcome_screen;

            return $welcome_screen;
        });
    }

    /**
     * Modify the guild's Welcome Screen. Requires the MANAGE_GUILD permission. Returns the updated Welcome Screen object.
     *
     * @param array $options An array of options.
     *                       enabled => whether the welcome screen is enabled
     *                       welcome_channels => channels linked in the welcome screen and their display options (maximum 5)
     *                       description => the server description to show in the welcome screen (maximum 140)
     *
     * @return ExtendedPromiseInterface
     */
    public function updateWelcomeScreen(array $options): ExtendedPromiseInterface
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined([
            'enabled',
            'welcome_channels',
            'description'
        ])
            ->setAllowedTypes('enabled', 'string')
            ->setAllowedTypes('welcome_channels', ['array', WelcomeScreen::class])
            ->setAllowedTypes('description', 'string');

        $options = $resolver->resolve($options);

        return $this->http->patch(Endpoint::bind(Endpoint::GUILD_WELCOME_SCREEN, $this->id), $options)->then(function ($response) {
            $welcome_screen = $this->discord->factory(WelcomeScreen::class, $response, true);
            $this->attributes['welcome_screen'] = $welcome_screen;

            return $welcome_screen;
        });
    }

    /**
     * Returns the Welcome Screen object for the guild.
     *
     * @return WelcomeScreen|null
     */
    public function getWelcomeScreenAttribute(): ?WelcomeScreen
    {
        return $this->attributes['welcome_screen'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getCreatableAttributes(): array
    {
        return [
            'name' => $this->name,
            'icon' => $this->attributes['icon'],
            'verification_level' => $this->verification_level,
            'default_message_notifications' => $this->default_message_notifications,
            'explicit_content_filter' => $this->explicit_content_filter,
            'afk_channel_id' => $this->afk_channel_id,
            'afk_timeout' => $this->afk_timeout,
            'system_channel_id' => $this->system_channel_id,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getUpdatableAttributes(): array
    {
        return [
            'name' => $this->name,
            'verification_level' => $this->verification_level,
            'default_message_notifications' => $this->default_message_notifications,
            'explicit_content_filter' => $this->explicit_content_filter,
            'afk_channel_id' => $this->afk_channel_id,
            'afk_timeout' => $this->afk_timeout,
            'icon' => $this->attributes['icon'],
            'splash' => $this->attributes['splash'],
            'banner' => $this->attributes['banner'],
            'system_channel_id' => $this->system_channel_id,
            'rules_channel_id' => $this->rules_channel_id,
            'public_updates_channel_id' => $this->public_updates_channel_id,
            'preferred_locale' => $this->preferred_locale,
            'premium_progress_bar_enabled' => $this->premium_progress_bar_enabled,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getRepositoryAttributes(): array
    {
        return [
            'guild_id' => $this->id,
            // Hack, should be only used for the bot's Application Guild Commands
            'application_id' => $this->discord->application->id,
        ];
    }

    /**
     * Returns the timestamp of when the guild was created.
     *
     * @return float
     */
    public function createdTimestamp()
    {
        return \Discord\getSnowflakeTimestamp($this->id);
    }
}
