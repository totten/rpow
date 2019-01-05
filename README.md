# CiviCRM Replay-on-Write Helper (civirpow)

This is a small utility which allows CiviCRM to work with an opportunistic
combination of read-only slave databases (RODB) and a read-write master
database (RWDB).  The general idea is to connect to RODB optimistically
(expecting a typical read-only use-case) -- and then switch to RWDB *if*
there is an actual write.

* [about.md](doc/about.md): More detailed discussion of the design
* [install.md](doc/install.md): Installation (non-development)
* [develop.md](doc/develop.md): Installation and experimentation (development)
