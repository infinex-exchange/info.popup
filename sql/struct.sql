CREATE ROLE "info.popup" LOGIN PASSWORD 'password';

create table popups(
    popupid serial not null primary key,
    title varchar(255) not null,
    body text not null,
    enabled boolean not null
);

GRANT SELECT, INSERT, UPDATE, DELETE ON popups TO "info.popup";
GRANT SELECT, USAGE ON popups_popupid_seq TO "info.popup";
