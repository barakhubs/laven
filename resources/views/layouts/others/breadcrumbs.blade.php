<ul class="breadcrumbs float-left">
    @php    
    $routeArray = request()->route()->getAction();
    $controllerAction = $routeArray['controller'];
    list($controller, $action) = explode('@', $controllerAction);
    $segments = ''; 
    @endphp

    @foreach(Request::segments() as $segment)
        @if ($segment == "dashboard")
            @php continue; @endphp
        @endif
        
        @php $segments .= '/'.$segment; @endphp
        
        @if(is_numeric($segment))
            @if (method_exists($controller, 'show'))
                @php $segment = 'View'; //continue; @endphp
            @else
                @php continue; @endphp
            @endif
        @endif

        @if(! ignoreRoutes($segments))
            @php continue; @endphp
        @endif
        
        @if(! $loop->last)
            <li>
                <a href="{{ url($segments) }}">{{ ucwords(str_replace("_"," ",$segment)) }}</a>
            </li>
        @else
            <li>
                <span>{{ ucwords(str_replace("_"," ",$segment)) }}</span>
            </li>
        @endif
    @endforeach
</ul>