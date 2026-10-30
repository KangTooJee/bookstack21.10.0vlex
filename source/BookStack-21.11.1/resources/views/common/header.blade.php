<header id="header" component="header-mobile-toggle" class="primary-background">
    <div class="grid mx-l">

        <div>
            <a href="{{ url('/') }}" class="logo">
                @if(setting('app-logo', '') !== 'none')
                    <img class="logo-image" src="{{ setting('app-logo', '') === '' ? url('/logo.png') : url(setting('app-logo', '')) }}" alt="Logo">
                @endif
                @if (setting('app-name-header'))
                    <span class="logo-text">{{ setting('app-name') }}</span>
                @endif
            </a>
            <button type="button"
                    refs="header-mobile-toggle@toggle"
                    title="{{ trans('common.header_menu_expand') }}"
                    aria-expanded="false"
                    class="mobile-menu-toggle hide-over-l">@icon('more')</button>
        </div>

        <div class="flex-container-row justify-center hide-under-l vlex-header-search">
            @if (hasAppAccess())
            <form action="{{ url('/search') }}" method="GET" role="search" style="width:100%; max-width:420px;">
                @include('search.parts.field', ['headerField' => true, 'inputValue' => $searchTerm ?? '', 'selectedScope' => request('scope', 'names')])
            </form>
            @endif
        </div>

        <div class="text-right">
            <nav refs="header-mobile-toggle@menu" class="header-links">
                <div class="links text-center">
                    @if (hasAppAccess())
                        <a class="hide-over-l" href="{{ url('/search') }}">@icon('search'){{ trans('common.search') }}</a>
                        @if(userCanOnAny('view', \BookStack\Entities\Models\Bookshelf::class) || userCan('bookshelf-view-all') || userCan('bookshelf-view-own'))
                            <a href="{{ url('/shelves') }}">@icon('bookshelf'){{ trans('entities.shelves') }}</a>
                        @endif
                        <a href="{{ url('/books') }}">@icon('books'){{ trans('entities.books') }}</a>
                        @if(signedInUser() && userCan('settings-manage'))
                            <a href="{{ url('/settings') }}">@icon('settings'){{ trans('settings.settings') }}</a>
                        @endif
                        @if(signedInUser() && userCan('users-manage') && !userCan('settings-manage'))
                            <a href="{{ url('/settings/users') }}">@icon('users'){{ trans('settings.users') }}</a>
                        @endif
                    @endif

                    @if(!signedInUser())
                        @if(setting('registration-enabled') && config('auth.method') === 'standard')
                            <a href="{{ url('/register') }}">@icon('new-user'){{ trans('auth.sign_up') }}</a>
                        @endif
                        <a href="{{ url('/login')  }}">@icon('login'){{ trans('auth.log_in') }}</a>
                    @endif
                </div>
                @if(signedInUser())
                    <?php $currentUser = user(); ?>
                    <div class="dropdown-container" component="dropdown" option:dropdown:bubble-escapes="true">
                        <span class="user-name py-s hide-under-l" refs="dropdown@toggle"
                              aria-haspopup="true" aria-expanded="false" aria-label="{{ trans('common.profile_menu') }}" tabindex="0">
                            <img class="avatar" src="{{$currentUser->getAvatar(30)}}" alt="{{ $currentUser->name }}">
                            <span class="name">{{ $currentUser->getShortName(9) }}</span> @icon('caret-down')
                        </span>
                        <ul refs="dropdown@menu" class="dropdown-menu" role="menu">
                            <li>
                                <a href="{{ url('/favourites') }}">@icon('star'){{ trans('entities.my_favourites') }}</a>
                            </li>
                            <li>
                                <a href="{{ $currentUser->getProfileUrl() }}">@icon('user'){{ trans('common.view_profile') }}</a>
                            </li>
                            <li>
                                <a href="{{ $currentUser->getEditUrl() }}">@icon('edit'){{ trans('common.edit_profile') }}</a>
                            </li>
                            <li>
                                <form action="{{ url(config('auth.method') === 'saml2' ? '/saml2/logout' : '/logout') }}"
                                      method="post">
                                    {{ csrf_field() }}
                                    <button class="text-muted icon-list-item text-primary">
                                        @icon('logout'){{ trans('auth.logout') }}
                                    </button>
                                </form>
                            </li>
                            <li><hr></li>
                            <li>
                                @include('common.dark-mode-toggle')
                            </li>
                        </ul>
                    </div>
                @endif
            </nav>
        </div>

    </div>
</header>
