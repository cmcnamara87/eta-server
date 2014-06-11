module.exports = function(grunt) {
    'use strict';

    require('load-grunt-tasks')(grunt);
    require('time-grunt')(grunt);

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        phplint: {
            options: {
                phpArgs: {
                    '-lf': null
                }
            },
            all: ['index.php', 'routes/**/*.php', 'middleware/**/*.php', 'push/**/*.php', 'db/**/*.php']
        },

        watch: {
            php: {
                files: ['index.php', 'routes/**/*.php', 'middleware/**/*.php', 'push/**/*.php', 'db/**/*.php'],
                tasks: ['phplint']
            }
        },

        rsync: {
            options: {
                args: ['--verbose'],
                exclude: ['.git*', '*.scss', 'node_modules'],
                recursive: true
            },
            // dist: {
            //     options: {
            //         src: './',
            //         dest: '../dist'
            //     }
            // },
            // stage: {
            //     options: {
            //         src: '../dist/',
            //         dest: '/var/www/site',
            //         host: 'user@staging-host',
            //         syncDestIgnoreExcl: true
            //     }
            // },
            prod: {
                options: {
                    src: '.',
                    dest: 'ec2-user@amazon:/var/www/html/eta/api',
                    ssh: true,
                    rescursive: true,
                    // syncDestIgnoreExcl: true,
                    // compareMode: 'checksum'
                }
            }
        },
    });

    grunt.registerTask('default', ['watch']);


};
