/* jshint unused:vars, undef:true, node:true */

var gulp = require('gulp'),
    cleanCss = require('gulp-clean-css'),
    concat = require('gulp-concat'),
    less = require('gulp-less'),
    rename = require('gulp-rename'),
    sourcemaps = require('gulp-sourcemaps'),
    uglify = require('gulp-uglify');

function less2css(sourceFile, destinationDirectory, newFilename, debug) {
    var g = gulp.src(sourceFile).pipe(less());
    if (!debug) {
        g = g.pipe(cleanCss({compatibility: 'ie8'}));
    }
    if (newFilename) {
        g = g.pipe(rename(newFilename));
    }
    g = g.pipe(gulp.dest(destinationDirectory));
    return g;
}

// Register CSS conversions
(function() {
    var releaseKeys = [], debugKeys = [];
    [
        ['css/online-translation.less', '../css'],
    ].forEach(function(data) {
        var key;
        key = 'css:' + data[0];
        gulp.task(key, function() {
            return less2css(data[0], data[1], data[2], false);
        });
        releaseKeys.push(key);
        gulp.task(key + '@debug', function() {
            return less2css(data[0], data[1], data[2], true);
        });
        debugKeys.push(key + '@debug');
    });
    gulp.task('css', releaseKeys);
    gulp.task('css-debug', debugKeys);
})();


gulp.task('default', ['css']);
