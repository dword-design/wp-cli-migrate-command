# WP-CLI Migrate Command

This command allows to migrate databases and upload directories across wordpress installations. URL replacements can be configured.

## Installation

Since the package index of WP-CLI is currently [on hold](https://github.com/wp-cli/wp-cli/issues/3977), the package has to be installed by hand:

    $ cd ~/.wp-cli/packages/
    $ composer require dword-design/wp-cli-migrate-command

## Usage

Configure your database connections in `wp-cli.yml`:

    databases:
      @local:
        host: localhost
        database: local_db_name
        user: local_db_user
        password: local_db_password
        domain-prefix: '//webiste.dev'

      @live:
        host: myserver.de
        database: live_db_name
        user: live_db_user
        password: live_db_password
        domain-prefix: '//www.website.de'

      ...

For upload migration, configure your SSH aliases in `wp-cli.yml`:

    @live:
      ssh: user@myserver.de
      path: www

Note that no entry is needed for local connections. In this case, the current directory is taken.

Lastly, if the upload directories differ from `wp-content/uploads`, configure them for each SSH alias:

    uploads:
      @local: 'some/special/dir'
      @live: 'another/special/dir'

If there is no corresponding SSH alias, the uploads folder is assumbed to be relative to the current directory.

Execute the `migrate` command:

    wp migrate <db|up> @sourceAlias @targetAlias

`wp migrate db @sourceAlias @targetAlias` migrates the database from a source database to a target database. Occurrence of the domain-prefix are replaced. Beware that the database is dropped and recreated on the target system.

`wp migrate up @sourceAlias @targetAlias` migrates the `uploads` folder via rsync. Beware that files are deleted on the target system that do not exist on the source system.

`wp migrate @sourceAlias @targetAlias` executes both tasks.