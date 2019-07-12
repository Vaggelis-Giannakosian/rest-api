<?php


    class TaskException extends Exception{}

    class Task {

        private $_id;
        private $_title;
        private $_description;
        private $_deadline;
        private $_completed;


        /**
         * Task constructor.
         * @param $id
         * @param $title
         * @param $description
         * @param $deadline
         * @param $completed
         * @throws TaskException
         */
        public function __construct($id, $title, $description, $deadline, $completed)
        {
                $this->setId($id);
                $this->setTitle($title);
                $this->setDescription($description);
                $this->setDeadline($deadline);
                $this->setCompleted($completed);

        }

        /**
         * @return mixed
         */
        public function getId()
        {
            return $this->_id;
        }

        /**
         * @param mixed $id
         * @throws TaskException
         */
        public function setId($id)
        {
            if(($id !==null) && (!is_numeric($id) || $id<=0 || $id>9223372036854775807 || $this->_id!== null ))
                throw new TaskException('Task ID error');

            $this->_id = $id;
        }

        /**
         * @return mixed
         */
        public function getTitle()
        {
            return $this->_title;
        }

        /**
         * @param mixed $title
         * @throws TaskException
         */
        public function setTitle($title)
        {
            if(strlen($title)<0 || strlen($title)>255) throw new TaskException('Task Title error');
            $this->_title = $title;
        }

        /**
         * @return mixed
         */
        public function getDescription()
        {
            return $this->_description;
        }

        /**
         * @param mixed $description
         * @throws TaskException
         */
        public function setDescription($description)
        {
            if($description!==null && strlen($description)>16777215) throw new TaskException('Task Description error');
            $this->_description = $description;
        }

        /**
         * @return mixed
         */
        public function getDeadline()
        {
            return $this->_deadline;
        }

        /**
         * @param mixed $deadline
         * @throws TaskException
         */
        public function setDeadline($deadline)
        {

            if($deadline!==null &&  date_format(date_create_from_format('d/m/Y H:i',$deadline),'d/m/Y H:i') !== $deadline  )throw new TaskException('Task Deadline date time error');

            $this->_deadline = $deadline;
        }

        /**
         * @return mixed
         */
        public function getCompleted()
        {
            return $this->_completed;
        }

        /**
         * @param mixed $completed
         * @throws TaskException
         */
        public function setCompleted($completed)
        {
            if(strtoupper($completed) !== 'Y' && strtoupper($completed) !== 'N' ) throw new TaskException('Task completed must be Y or N');
            $this->_completed = $completed;
        }


        public function returnTaskAsArray()
        {
            $task = array();
            $task['id'] = $this->getId();
            $task['title'] = $this->getTitle();
            $task['description'] = $this->getDescription();
            $task['deadline'] = $this->getDeadline();
            $task['completed'] = $this->getCompleted();

            return $task;
        }


    }
