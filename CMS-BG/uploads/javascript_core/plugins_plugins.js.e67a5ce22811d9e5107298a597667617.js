var scrollToTopJS=(function($,window){$(document).ready(function(){$(document).on('scroll',function(){if($(window).scrollTop()>5){$('.scroll-top-wrapper').addClass('show');}else{$('.scroll-top-wrapper').removeClass('show');}});$('.scroll-top-wrapper').on('click',(function(){$('html, body').animate({scrollTop:0},250,'linear');}));});}(jQuery,window));;