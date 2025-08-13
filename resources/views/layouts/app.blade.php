<!doctype html>
<html class="{{ $themeMode }}" lang="en" dir="{{ $appDirection }}">

@include('layouts.head')

<body>
	<!-- Page Loader -->
	@include('layouts.page-loader')

	<!--wrapper-->
	<div class="wrapper">
		@include('layouts.navigation')
		
        @include('layouts.header')
        
		@yield('content')
		
		@include('layouts.footer')
	</div>
	<!--end wrapper-->


	{{-- @include('layouts.search') --}}

	{{-- @include('layouts.switcher') --}}
	
	@include('layouts.script')

</body>

</html>