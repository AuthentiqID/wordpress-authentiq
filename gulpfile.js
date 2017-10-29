var gulp = require('gulp'),
  gutil = require('gulp-util'),
  sass = require('gulp-sass'),
  autoprefixer = require('gulp-autoprefixer'),
  minifycss = require('gulp-minify-css'),
  sourcemaps = require('gulp-sourcemaps'),
  jshint = require('gulp-jshint'),
  uglify = require('gulp-uglify'),
  rename = require('gulp-rename'),
  notify = require('gulp-notify'),
  livereload = require('gulp-livereload'),
  header = require('gulp-header'),
  lr = require('tiny-lr'),
  plumber = require('gulp-plumber'),
  runSequence = require('run-sequence'),
  zip = require('gulp-zip'),
  server = lr();

var pkg = require('./package.json');
var banner = '/*!\n' +
  ' * <%= pkg.title %> - v<%= pkg.version %>\n' +
  ' *\n' +
  ' * <%= pkg.description %>\n' +
  ' *\n' +
  ' *\n' +
  ' * @author <%= pkg.author.name %>\n' +
  ' * @website <%= pkg.homepage %>\n' +
  ' *\n' +
  ' * @copyright Authentiq <%= new Date().getFullYear() %>\n' +
  ' * @license under <%= pkg.license.type %> (<%= pkg.license.url %>)\n' +
  ' */\n';

gulp.task('default', function () {
  gulp.start('sass', 'js');
});

gulp.task('sass', function () {
  return gulp.src([
    './{admin,public}/sass/**/*.scss',
    '!./node_modules{,/**}'
  ])
    .pipe(plumber())
    .pipe(sourcemaps.init())
    .pipe(sass({style: 'expanded', bare: true})).on('error', gutil.log)
    .pipe(autoprefixer('last 2 version', 'safari 5', 'ie 8', 'ie 9', 'opera 12.1', 'ios 6', 'android 4'))
    .pipe(sourcemaps.write())
    .pipe(rename(function (path) {
      path.dirname = path.dirname.replace('sass', 'css');
    }))
    .pipe(gulp.dest(''))
    .pipe(rename({suffix: '.min'}))
    .pipe(minifycss())
    .pipe(gulp.dest(''))
    .pipe(livereload(server))
    .pipe(notify({message: 'Styles compiled.'}));
});


gulp.task('js', function () {
  return gulp.src([
    './{admin,public}/js/**/*.js',
    '!./{admin,public}/js/**/*.min.js',
    '!./node_modules{,/**}'
  ])
    .pipe(jshint())
    .pipe(jshint.reporter('jshint-stylish'))
    .pipe(jshint.reporter('fail'))
    .pipe(rename({suffix: '.min'}))
    .pipe(uglify())
    .pipe(header(banner, {pkg: pkg}))
    .pipe(gulp.dest(''))
    .pipe(livereload(server))
    .pipe(notify({message: 'Scripts compiled.'}));
});

gulp.task('build', function () {
  runSequence(
    ['sass', 'js'],
    function () {
      gulp.src(
        [
          'admin/**',
          'languages/**',
          'includes/**',
          'public/**',
          'uninstall.php',
          'authentiq.php'
        ],
        {base: './'}
      ).pipe(gulp.dest('build/authentiq-wordpress/'))
        .pipe(zip('authentiq-wordpress.zip'))
        .pipe(gulp.dest('build/'));
    }
  );
});

gulp.task('watch', function () {
  server.listen(35729, function (err) {
    if (err) {
      gutil.log(err);
    }
  });

  gulp.watch('./{admin,public}/sass/**/*.scss', ['sass']);
  gulp.watch([
    './{admin,public}/js/**/*.js',
    '!./{admin,public}/js/**/*.min.js',
  ], ['js']);
});
