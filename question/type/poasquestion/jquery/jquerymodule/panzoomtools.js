/**
 * Script for adding panzoom to mistakes images.
 *
 * @copyright &copy; 2016 Oleg Sychev, Volgograd State Technical University
 * @author Matyushechkin Dmitry, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */
 
/**
 * Calling this object adds panzoom to every image of 'img_panzoom' class.
 */
M.panzoomtools = (function($) {

    return function() {
        $(document).ready(function() {
            var panzoomimages = $('.img_panzoom');

            panzoomimages.panzoom();

            panzoomimages.on('mousewheel.focal', function(e) {
                e.preventDefault();
                var delta = e.delta || e.originalEvent.wheelDelta;
                var zoomOut = delta ? delta < 0 : e.originalEvent.deltaY > 0;
                var panzoomholder = $(e.target).parents(".img_panzoom")[0];
                $(panzoomholder).panzoom('zoom', zoomOut, {
                    increment: 0.1,
                    focal: e
                });
            });
        });
    };

})(jQuery);