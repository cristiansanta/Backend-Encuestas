--
-- PostgreSQL database dump
--

-- Dumped from database version 16.3 (Ubuntu 16.3-1.pgdg22.04+1)
-- Dumped by pg_dump version 16.3 (Ubuntu 16.3-1.pgdg22.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: Produc; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA "Produc";


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: categories; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".categories (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    descrip_cat character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".categories_id_seq OWNED BY "Produc".categories.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".failed_jobs_id_seq OWNED BY "Produc".failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".jobs_id_seq OWNED BY "Produc".jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".migrations_id_seq OWNED BY "Produc".migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: question_options; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".question_options (
    id bigint NOT NULL,
    questions_id integer NOT NULL,
    options character varying(255) NOT NULL,
    creator_id integer NOT NULL,
    status character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: question_options_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".question_options_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: question_options_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".question_options_id_seq OWNED BY "Produc".question_options.id;


--
-- Name: questions; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".questions (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    descrip character varying(255) NOT NULL,
    validate character varying(255) NOT NULL,
    related_question character varying(255) NOT NULL,
    bank boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    type_questions_id integer,
    creator_id integer
);


--
-- Name: questions_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".questions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: questions_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".questions_id_seq OWNED BY "Produc".questions.id;


--
-- Name: sections; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".sections (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    descrip_sect character varying(255) NOT NULL,
    id_survey integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sections_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".sections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sections_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".sections_id_seq OWNED BY "Produc".sections.id;


--
-- Name: sessions; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: survey_answers; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".survey_answers (
    id bigint NOT NULL,
    survey_question_id integer NOT NULL,
    answer integer NOT NULL,
    creator_id integer NOT NULL,
    status boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_answers_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".survey_answers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: survey_answers_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".survey_answers_id_seq OWNED BY "Produc".survey_answers.id;


--
-- Name: survey_questions; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".survey_questions (
    id bigint NOT NULL,
    survey_id integer NOT NULL,
    question_id integer NOT NULL,
    section_id integer NOT NULL,
    creator_id integer NOT NULL,
    status boolean NOT NULL,
    user_id character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: survey_questions_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".survey_questions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: survey_questions_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".survey_questions_id_seq OWNED BY "Produc".survey_questions.id;


--
-- Name: surveys; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".surveys (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    descrip character varying(255) NOT NULL,
    id_category integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status boolean
);


--
-- Name: surveys_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".surveys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: surveys_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".surveys_id_seq OWNED BY "Produc".surveys.id;


--
-- Name: type_questions; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".type_questions (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    descrip character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: type_questions_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".type_questions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: type_questions_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".type_questions_id_seq OWNED BY "Produc".type_questions.id;


--
-- Name: users; Type: TABLE; Schema: Produc; Owner: -
--

CREATE TABLE "Produc".users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: Produc; Owner: -
--

CREATE SEQUENCE "Produc".users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: Produc; Owner: -
--

ALTER SEQUENCE "Produc".users_id_seq OWNED BY "Produc".users.id;


--
-- Name: categories id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".categories ALTER COLUMN id SET DEFAULT nextval('"Produc".categories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".failed_jobs ALTER COLUMN id SET DEFAULT nextval('"Produc".failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".jobs ALTER COLUMN id SET DEFAULT nextval('"Produc".jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".migrations ALTER COLUMN id SET DEFAULT nextval('"Produc".migrations_id_seq'::regclass);


--
-- Name: question_options id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".question_options ALTER COLUMN id SET DEFAULT nextval('"Produc".question_options_id_seq'::regclass);


--
-- Name: questions id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".questions ALTER COLUMN id SET DEFAULT nextval('"Produc".questions_id_seq'::regclass);


--
-- Name: sections id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".sections ALTER COLUMN id SET DEFAULT nextval('"Produc".sections_id_seq'::regclass);


--
-- Name: survey_answers id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_answers ALTER COLUMN id SET DEFAULT nextval('"Produc".survey_answers_id_seq'::regclass);


--
-- Name: survey_questions id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_questions ALTER COLUMN id SET DEFAULT nextval('"Produc".survey_questions_id_seq'::regclass);


--
-- Name: surveys id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".surveys ALTER COLUMN id SET DEFAULT nextval('"Produc".surveys_id_seq'::regclass);


--
-- Name: type_questions id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".type_questions ALTER COLUMN id SET DEFAULT nextval('"Produc".type_questions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".users ALTER COLUMN id SET DEFAULT nextval('"Produc".users_id_seq'::regclass);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: question_options question_options_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".question_options
    ADD CONSTRAINT question_options_pkey PRIMARY KEY (id);


--
-- Name: questions questions_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".questions
    ADD CONSTRAINT questions_pkey PRIMARY KEY (id);


--
-- Name: sections sections_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".sections
    ADD CONSTRAINT sections_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: survey_answers survey_answers_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_answers
    ADD CONSTRAINT survey_answers_pkey PRIMARY KEY (id);


--
-- Name: survey_questions survey_questions_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_questions
    ADD CONSTRAINT survey_questions_pkey PRIMARY KEY (id);


--
-- Name: surveys surveys_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".surveys
    ADD CONSTRAINT surveys_pkey PRIMARY KEY (id);


--
-- Name: type_questions type_questions_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".type_questions
    ADD CONSTRAINT type_questions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: Produc; Owner: -
--

CREATE INDEX jobs_queue_index ON "Produc".jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: Produc; Owner: -
--

CREATE INDEX sessions_last_activity_index ON "Produc".sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: Produc; Owner: -
--

CREATE INDEX sessions_user_id_index ON "Produc".sessions USING btree (user_id);


--
-- Name: question_options question_options_questions_id_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".question_options
    ADD CONSTRAINT question_options_questions_id_foreign FOREIGN KEY (questions_id) REFERENCES "Produc".questions(id);


--
-- Name: questions questions_type_questions_id_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".questions
    ADD CONSTRAINT questions_type_questions_id_foreign FOREIGN KEY (type_questions_id) REFERENCES "Produc".type_questions(id);


--
-- Name: sections sections_id_survey_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".sections
    ADD CONSTRAINT sections_id_survey_foreign FOREIGN KEY (id_survey) REFERENCES "Produc".surveys(id);


--
-- Name: survey_answers survey_answers_survey_question_id_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_answers
    ADD CONSTRAINT survey_answers_survey_question_id_foreign FOREIGN KEY (survey_question_id) REFERENCES "Produc".survey_questions(id);


--
-- Name: survey_questions survey_questions_question_id_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_questions
    ADD CONSTRAINT survey_questions_question_id_foreign FOREIGN KEY (question_id) REFERENCES "Produc".questions(id);


--
-- Name: survey_questions survey_questions_survey_id_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".survey_questions
    ADD CONSTRAINT survey_questions_survey_id_foreign FOREIGN KEY (survey_id) REFERENCES "Produc".surveys(id);


--
-- Name: surveys surveys_id_category_foreign; Type: FK CONSTRAINT; Schema: Produc; Owner: -
--

ALTER TABLE ONLY "Produc".surveys
    ADD CONSTRAINT surveys_id_category_foreign FOREIGN KEY (id_category) REFERENCES "Produc".categories(id);


--
-- PostgreSQL database dump complete
--

